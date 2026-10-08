<?php
namespace App\Http\Controllers;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
class PublicController extends Controller {
 public function home(){return view('public.home',['programs'=>DB::table('programs')->where('status','published')->get(),'courses'=>DB::table('courses')->where('status','published')->latest()->limit(6)->get(),'posts'=>DB::table('posts')->where('status','published')->latest()->limit(3)->get()]);}
 public function page(string $slug){
  $page=DB::table('pages')->where('slug',$slug)->where('status','published')->first();
  if(!$page&&$slug==='about')$page=(object)[
   'slug'=>'about','title'=>'About us','meta_description'=>'Meet Lwotowone Enterprises Ltd, a Ugandan enterprise connecting practical skills with opportunity.',
    'body'=>"We are a Ugandan enterprise built around a simple belief: practical education and enterprise development can help people build a livelihood.\n\nOur work brings together vocational learning, entrepreneurship, mentorship and opportunities for young people and communities. We focus on learning people can put to use, ideas they can explore and confidence they can build through experience.",
  ];
  abort_unless($page,404);return view('public.page',compact('page'));
 }
 public function listing(Request $r,string $type){abort_unless(in_array($type,['programs','courses','opportunities','events','posts']),404);$q=DB::table($type)->where('status','published');if($type==='opportunities')$q->whereDate('deadline','>=',today());if($s=$r->query('q'))$q->where('title','like','%'.$s.'%');$rows=$q->latest()->paginate(12)->withQueryString();return view('public.list',compact('rows','type'));}
 public function detail(string $type,string $id){abort_unless(in_array($type,['programs','courses','opportunities','events','posts']),404);$record=DB::table($type)->where('id',$id)->where('status','published')->first();abort_unless($record,404);return view('public.detail',compact('record','type'));}
 public function contact(){return view('public.contact');}
 public function sendContact(Request $r){$d=$r->validate(['name'=>'required|string|max:100','email'=>'required|email|max:190','message'=>'required|string|max:5000']);DB::table('contacts')->insert($d+['created_at'=>now(),'updated_at'=>now()]);return back()->with('success','Thank you. Your enquiry has been received.');}
}
