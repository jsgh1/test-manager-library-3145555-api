<?php
namespace App\Http\Controllers;
use App\Support\ApiError;
use App\Support\Jwt;
use App\Domain\Loan;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Routing\Controller;
use Carbon\CarbonImmutable;
final class ApiController extends Controller {
    public function __construct(
        private readonly \App\Application\Services\LendBooksService $lender,
        private readonly \App\Application\Services\ReturnItemService $returns,
        private readonly \App\Application\Services\LoginService $loginService,
        private readonly \App\Application\Services\CreateLibrarianService $createLibrarian,
        private readonly \App\Application\Services\MostBorrowedReportService $reportService
    ) {}
    private function id(): string {return (string) Str::uuid();}
    private function fail(string $code,int $status,string $message,array $details=[]): never {throw new ApiError($code,$status,$message,$details);}
    private function required(Request $r,array $rules): array {return $r->validate($rules);}
    private function page(Request $r): array {
        $v=$this->required($r,['page'=>'sometimes|integer|min:1','size'=>'sometimes|integer|min:1|max:50']);
        return [(int)($v['page']??1),(int)($v['size']??20)];
    }
    private function paged($query,Request $r,?callable $map=null): array {
        [$page,$size]=$this->page($r);
        $total=(clone $query)->count();
        $items=$query->offset(($page-1)*$size)->limit($size)->get()->map(fn($x)=>$map?$map($x):(array)$x)->all();
        return compact('items','page','size','total');
    }
    private function bookRow($b): array {
        $c=DB::table('library.category')->where('id',$b->category_id)->first();
        return ['id'=>$b->id,'title'=>$b->title,'author'=>$b->author,'isbn'=>trim($b->isbn),
            'category'=>['id'=>$c->id,'name'=>$c->name], 'total_copies'=>$b->total_copies,
            'available_copies'=>$b->available_copies,'cover_key'=>$b->cover_key];
    }
    private function activeBook(string $id) {
        $b=DB::table('library.book')->where('id',$id)->whereNull('deleted_at')->first();
        if(!$b)$this->fail('NOT_FOUND',404,'Book not found.');
        return $b;
    }
    private function activeMember(string $id) {
        $m=DB::table('library.member')->where('id',$id)->first();
        if(!$m)$this->fail('NOT_FOUND',404,'Member not found.');
        return $m;
    }
    public function login(Request $r)
    {
        $v = $this->required($r, ['username' => 'required|string', 'password' => 'required|string']);
        return $this->loginService->execute($v['username'], $v['password']);
    }

    public function createUser(Request $r)
    {
        $v = $this->required($r, [
            'username' => 'required|string|max:120',
            'password' => 'required|string|min:8',
            'role' => 'required|string',
        ]);
        return response()->json(
            $this->createLibrarian->execute($v['username'], $v['password'], $v['role']),
            201
        );
    }
    public function categories() {return DB::table('library.category')->orderBy('name')->get();}
    public function books(Request $r) {
        $q=DB::table('library.book')->whereNull('deleted_at');
        if($r->filled('q')) {
            $s=(string)$r->query('q');
            $q->where(fn($z)=>$z->where('title','ilike',"%$s%")->orWhere('author','ilike',"%$s%"));
        }
        if($r->filled('category_id'))$q->where('category_id',$r->query('category_id'));
        return $this->paged($q->orderBy('title')->orderBy('id'),$r,fn($x)=>$this->bookRow($x));
    }
    public function book(string $id) {return $this->bookRow($this->activeBook($id));}
    private function bookPayload(Request $r): array {
        return $this->required($r,[
           'title'=>'required|string|max:250','author'=>'required|string|max:200',
           'isbn'=>'sometimes|digits:13','category_id'=>'required|uuid',
           'total_copies'=>'required|integer|min:1']);
    }
    private function categoryExists(string $id): void {
        if(!DB::table('library.category')->where('id',$id)->exists())$this->fail('VALIDATION_ERROR',422,'Invalid category.');
    }
    public function createBook(Request $r) {
        $v=$this->required($r,['title'=>'required|string|max:250','author'=>'required|string|max:200',
             'isbn'=>'required|digits:13','category_id'=>'required|uuid','total_copies'=>'required|integer|min:1']);
        $this->categoryExists($v['category_id']);
        if(DB::table('library.book')->where('isbn',$v['isbn'])->whereNull('deleted_at')->exists())$this->fail('ISBN_TAKEN',409,'ISBN already exists.');
        $id=$this->id();
        DB::table('library.book')->insert([
         'id'=>$id,'title'=>trim($v['title']),'author'=>trim($v['author']),'isbn'=>$v['isbn'],
         'category_id'=>$v['category_id'],'total_copies'=>$v['total_copies'],'available_copies'=>$v['total_copies'],
         'cover_key'=>null,'deleted_at'=>null
        ]);
        return response()->json($this->bookRow($this->activeBook($id)),201);
    }
    public function updateBook(Request $r,string $id) {
        $v=$this->required($r,['title'=>'required|string|max:250','author'=>'required|string|max:200','category_id'=>'required|uuid','total_copies'=>'required|integer|min:1']);
        $this->categoryExists($v['category_id']);
        DB::transaction(function()use($id,$v){
            $b=DB::table('library.book')->where('id',$id)->whereNull('deleted_at')->lockForUpdate()->first();
            if(!$b)$this->fail('NOT_FOUND',404,'Book not found.');
            $available=$b->available_copies+$v['total_copies']-$b->total_copies;
            if($available<0)$this->fail('VALIDATION_ERROR',422,'Total copies cannot fall below checked-out copies.');
            DB::table('library.book')->where('id',$id)->update([
                'title'=>trim($v['title']),'author'=>trim($v['author']),
                'category_id'=>$v['category_id'],'total_copies'=>$v['total_copies'],'available_copies'=>$available
            ]);
        });
        return $this->book($id);
    }
    public function deleteBook(string $id) {
        DB::transaction(function()use($id){
            $b=DB::table('library.book')->where('id',$id)->whereNull('deleted_at')->lockForUpdate()->first();
            if(!$b)$this->fail('NOT_FOUND',404,'Book not found.');
            if(DB::table('library.loan_item')->where('book_id',$id)->whereNull('returned_at')->exists())$this->fail('BOOK_HAS_OPEN_LOANS',409,'Book has open loans.');
            DB::table('library.book')->where('id',$id)->update(['deleted_at'=>now('UTC')]);
        });
        return response()->noContent();
    }
    private function coversDir(): string {return (string)env('COVERS_DIR',storage_path('covers'));}
    public function putCover(Request $r,string $id) {
        $this->activeBook($id);
        $v=$this->required($r,['file'=>'required|file|max:2048']);
        $f=$r->file('file');
        $mime=(new \finfo(FILEINFO_MIME_TYPE))->file($f->getRealPath());
        if(!in_array($mime,['image/jpeg','image/png'],true))$this->fail('VALIDATION_ERROR',422,'Only JPEG and PNG covers are accepted.');
        $key=$this->id().($mime==='image/png'?'.png':'.jpg');$dir=$this->coversDir();
        if(!is_dir($dir)&&!mkdir($dir,0770,true)&&!is_dir($dir))throw new \RuntimeException('Storage unavailable');
        $f->move($dir,$key);
        DB::table('library.book')->where('id',$id)->update(['cover_key'=>$key]);
        return $this->book($id);
    }
    public function getCover(string $id) {
        $b=$this->activeBook($id);
        if(!$b->cover_key)$this->fail('NOT_FOUND',404,'Cover not found.');
        $path=$this->coversDir().'/'.basename($b->cover_key);
        if(!is_file($path))$this->fail('NOT_FOUND',404,'Cover not found.');
        return response()->file($path,['Content-Type'=>(new \finfo(FILEINFO_MIME_TYPE))->file($path)]);
    }
    public function deleteCover(string $id) {
        $b=$this->activeBook($id);
        DB::table('library.book')->where('id',$id)->update(['cover_key'=>null]);
        if($b->cover_key)@unlink($this->coversDir().'/'.basename($b->cover_key));
        return response()->noContent();
    }
    public function members(Request $r) {
        $q=DB::table('library.member');
        if($r->filled('q')) { $s=(string)$r->query('q');$q->where(fn($z)=>$z->where('full_name','ilike',"%$s%")->orWhere('document_number','ilike',"%$s%")); }
        return $this->paged($q->orderBy('full_name')->orderBy('id'),$r);
    }
    public function member(string $id) {
        $m=$this->activeMember($id);
        $count=DB::table('library.loan_item as li')->join('library.loan as l','l.id','=','li.loan_id')
           ->where('l.member_id',$id)->whereNull('li.returned_at')->count();
        return (array)$m+['active_loans'=>$count];
    }
    public function createMember(Request $r) {
        $v=$this->required($r,['full_name'=>'required|string|max:200','document_number'=>'required|string|max:30']);
        $doc=trim($v['document_number']);
        if(DB::table('library.member')->where('document_number',$doc)->exists())$this->fail('DOCUMENT_TAKEN',409,'Document number already exists.');
        $row=['id'=>$this->id(),'full_name'=>trim($v['full_name']),'document_number'=>$doc];
        DB::table('library.member')->insert($row);
        return response()->json($row,201);
    }
    private function loanRow(string $id): array {
        $l=DB::table('library.loan')->where('id',$id)->first();
        if(!$l)$this->fail('NOT_FOUND',404,'Loan not found.');
        $items=DB::table('library.loan_item')->where('loan_id',$id)->orderBy('id')->get()->map(fn($i)=>[
          'id'=>$i->id,'book_id'=>$i->book_id,'book_title'=>$i->book_title,'isbn'=>trim($i->isbn),
          'category_name'=>$i->category_name,'due_at'=>CarbonImmutable::parse($i->due_at)->utc()->format('Y-m-d\TH:i:s\Z'),
          'returned_at'=>$i->returned_at?CarbonImmutable::parse($i->returned_at)->utc()->format('Y-m-d\TH:i:s\Z'):null
        ])->all();
        return ['id'=>$l->id,'member_id'=>$l->member_id,'loaned_at'=>CarbonImmutable::parse($l->loaned_at)->utc()->format('Y-m-d\TH:i:s\Z'),
          'status'=>Loan::status($items,new \DateTimeImmutable('now',new \DateTimeZone('UTC'))),'items'=>$items];
    }
    public function lend(Request $r)
    {
        $v = $this->required($r, [
            'member_id' => 'required|uuid',
            'items' => 'required|array|min:1|max:3',
            'items.*.book_id' => 'required|uuid',
        ]);
        $id = $this->lender->execute(
            $v['member_id'],
            array_column($v['items'], 'book_id'),
            $r->attributes->get('operator')['id']
        );
        return response()->json($this->loanRow($id), 201);
    }

    public function returnItem(string $id, string $item)
    {
        return response()->json($this->returns->execute($id, $item));
    }
    public function loan(string $id) {return $this->loanRow($id);}
    public function loans(Request $r) {
        $this->required($r,['from'=>'sometimes|date_format:Y-m-d','to'=>'sometimes|date_format:Y-m-d',
               'status'=>'sometimes|in:open,overdue,closed','member_id'=>'sometimes|uuid']);
        $q=DB::table('library.loan');
        if($r->filled('from'))$q->whereDate('loaned_at','>=',$r->query('from'));
        if($r->filled('to'))$q->whereDate('loaned_at','<=',$r->query('to'));
        if($r->filled('member_id'))$q->where('member_id',$r->query('member_id'));
        if($r->filled('status')){
          $state=$r->query('status');
          $open=fn($z)=>$z->selectRaw('1')->from('library.loan_item as x')->whereColumn('x.loan_id','library.loan.id')->whereNull('x.returned_at');
          $overdue=fn($z)=>$z->selectRaw('1')->from('library.loan_item as y')->whereColumn('y.loan_id','library.loan.id')->whereNull('y.returned_at')->where('y.due_at','<',now('UTC'));
          if($state==='closed')$q->whereNotExists($open);
          if($state==='open')$q->whereExists($open)->whereNotExists($overdue);
          if($state==='overdue')$q->whereExists($overdue);
        }
        return $this->paged($q->orderByDesc('loaned_at')->orderBy('id'),$r,fn($x)=>$this->loanRow($x->id));
    }
    public function overdue(Request $r) {
        $q=DB::table('library.loan_item as li')->join('library.loan as l','l.id','=','li.loan_id')
          ->join('library.member as m','m.id','=','l.member_id')
          ->whereNull('li.returned_at')->where('li.due_at','<',CarbonImmutable::now('UTC'))
          ->select('l.id as loan_id','li.id as item_id','li.book_title','m.full_name as member_name','li.due_at')
          ->orderBy('li.due_at')->orderBy('li.id');
        $data=$this->paged($q,$r,fn($x)=>[
            'loan_id'=>$x->loan_id,'item_id'=>$x->item_id,'book_title'=>$x->book_title,
            'member_name'=>$x->member_name,'due_at'=>CarbonImmutable::parse($x->due_at)->utc()->format('Y-m-d\TH:i:s\Z'),
            'days_late'=>max(1,(int)floor(CarbonImmutable::parse($x->due_at)->diffInSeconds(CarbonImmutable::now('UTC'))/86400))
        ]);
        return $data;
    }
    public function report(Request $r)
    {
        $v = $this->required($r, [
            'from' => 'required|date_format:Y-m-d',
            'to' => 'required|date_format:Y-m-d',
        ]);
        return $this->reportService->execute($v['from'], $v['to']);
    }
}
