<?php
namespace App\Http\Controllers;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
class PublicController extends Controller {
 public function help(string $page='faq'){
  abort_unless(in_array($page,['faq','guide']),404);
  return view('public.help',compact('page'));
 }
 public function chat(Request $r){
  $data=$r->validate(['message'=>'required|string|max:1000']);
  $question=trim($data['message']);
  $reply=app(\App\Services\AiAssistant::class)->complete(
   'You are the Lwotowone platform help assistant. Answer warmly and concisely using only these facts: Lwotowone offers practical learning, courses, programmes, mentorship, events and opportunities in Uganda. Users can browse /explore/courses, /explore/programs and /explore/opportunities; account help is at /user-guide; common questions are at /faq; human support is at /contact. Do not request passwords or sensitive personal information. If unsure, say so and direct the person to /contact.',
   $question,
   240,
  );
  $usedAi=(bool)$reply;
  if(!$reply){$text=mb_strtolower($question);$reply=match(true){preg_match('/access|disab|pwd|contrast|font|text size/',$text)===1=>'Use Accessibility options beside Need help? to adjust text size, reading font, spacing, colour display, links, motion or read-aloud. For individual accommodations, contact our team.',preg_match('/course|learn|programme|program|training/',$text)===1=>'Browse courses at /explore/courses or programmes at /explore/programs. Open an item to read the details and next steps.',preg_match('/account|login|sign in|password|register|join/',$text)===1=>'Create an account at /register, sign in at /login or use the password reset link. The step-by-step guide is at /user-guide.',preg_match('/opportunit|job|apply|deadline/',$text)===1=>'Explore current listings at /explore/opportunities. Each page shows requirements, location and deadline.',preg_match('/mentor|event|business|enterprise/',$text)===1=>'Sign in and look under Grow and connect for mentorship, events, enterprise learning and support.',preg_match('/human|person|contact|talk|help/',$text)===1=>'Our team is happy to help. Send us a message at /contact, or see common answers at /faq.',default=>'I can help you find courses, programmes, opportunities, account support and accessibility options. Visit /faq or contact our team at /contact.'};}
  return response()->json(['reply'=>$reply,'ai'=>$usedAi]);
 }
 public function search(Request $r){
  $data=$r->validate(['q'=>'required|string|max:255']);
  $escaped=str_replace(['!','%','_'],['!!','!%','!_'],$data['q']);$pattern='%'.$escaped.'%';$results=collect();
  $sources=[
   'programs'=>['Programmes',['title','summary','body']],
   'courses'=>['Courses',['title','summary']],
   'opportunities'=>['Opportunities',['title','organisation','location','description']],
   'events'=>['Events',['title','description','location']],
   'posts'=>['Stories',['title','body']],
   'pages'=>['Pages',['title','body','meta_description']],
  ];
  foreach($sources as $table=>[$label,$fields]){
   $query=DB::table($table)->where('status','published')->where(function($q)use($fields,$pattern){foreach($fields as $i=>$field){$method=$i===0?'whereRaw':'orWhereRaw';$q->{$method}("{$field} LIKE ? ESCAPE '!'",[$pattern]);}});
   if($table==='opportunities')$query->whereDate('deadline','>=',today());
   $query->orderByDesc('id')->limit(10)->get()->each(function($record)use($results,$table,$label){
    $results->push(['type'=>$label,'title'=>$record->title,'summary'=>$record->summary??$record->description??$record->meta_description??$record->body??'','url'=>$table==='pages'?'/pages/'.$record->slug:'/explore/'.$table.'/'.$record->id]);
   });
  }
  return view('public.search',['query'=>$data['q'],'results'=>$results]);
 }
 public function home(){return view('public.home',['programs'=>DB::table('programs')->where('status','published')->get(),'courses'=>DB::table('courses')->where('status','published')->latest()->limit(6)->get(),'posts'=>DB::table('posts')->where('status','published')->latest()->limit(3)->get()]);}
 public function page(string $slug){
  $page=DB::table('pages')->where('slug',$slug)->where('status','published')->first();
  if(!$page&&$slug==='about')$page=(object)[
   'slug'=>'about','title'=>'About us','meta_description'=>'Meet Lwotowone Enterprises Ltd, a Ugandan enterprise connecting practical skills with opportunity.',
    'body'=>"We are a Ugandan enterprise built around a simple belief: practical education and enterprise development can help people build a livelihood.\n\nOur work brings together vocational learning, entrepreneurship, mentorship and opportunities for young people and communities. We focus on learning people can put to use, ideas they can explore and confidence they can build through experience.",
  ];
  abort_unless($page,404);return view('public.page',compact('page'));
 }
 public function listing(Request $r,string $type){abort_unless(in_array($type,['programs','courses','opportunities','events','posts']),404);$q=DB::table($type)->where('status','published');if($type==='opportunities')$q->whereDate('deadline','>=',today());$rows=$q->latest()->paginate(12)->withQueryString();return view('public.list',compact('rows','type'));}
 public function detail(string $type,string $id){abort_unless(in_array($type,['programs','courses','opportunities','events','posts']),404);$record=DB::table($type)->where('id',$id)->where('status','published')->first();abort_unless($record,404);return view('public.detail',compact('record','type'));}
 public function contact(){return view('public.contact');}
 public function sendContact(Request $r){$d=$r->validate(['name'=>'required|string|max:100','email'=>'required|email|max:190','message'=>'required|string|max:5000']);DB::table('contacts')->insert($d+['created_at'=>now(),'updated_at'=>now()]);return back()->with('success','Thank you. Your enquiry has been received.');}
}
