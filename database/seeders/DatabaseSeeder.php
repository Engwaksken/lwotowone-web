<?php
namespace Database\Seeders;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\{DB,Hash};
use App\Models\User;
class DatabaseSeeder extends Seeder {
 public function run(): void {
  $email=env('ADMIN_EMAIL');$password=env('ADMIN_PASSWORD');
  if(!$email||strlen((string)$password)<12)throw new \RuntimeException('Set ADMIN_EMAIL and a unique ADMIN_PASSWORD of at least 12 characters before seeding.');
  User::firstOrCreate(['email'=>$email],['name'=>'Lwotowone Administrator','password'=>Hash::make($password),'role'=>'admin','status'=>'active']);
  foreach(['hero_eyebrow'=>'Practical learning. Sustainable livelihoods.','hero_title'=>"Growing skills.\nCreating opportunities.\nTransforming lives.",'hero_summary'=>'Discover practical education, vocational training and mentorship that help young people in Uganda turn potential into livelihoods.'] as $key=>$value)if(!DB::table('settings')->where('key',$key)->exists())DB::table('settings')->insert(compact('key','value')+['created_at'=>now(),'updated_at'=>now()]);
  $pages=require database_path('seeders/content.php');
  foreach($pages as $slug=>$p)\App\Models\Record::for('pages')->newQuery()->firstOrCreate(['slug'=>$slug],['title'=>$p[0],'body'=>$p[1],'status'=>'published','meta_description'=>$p[2]??$p[0],'created_at'=>now(),'updated_at'=>now()]);
  foreach([
   ['Practical education and skills development','practical-education','Youth empowerment','Learning by doing connects knowledge to real experience.','Practical demonstrations, hands-on training, mentorship, enterprise projects and exposure to real business environments help learners understand how skills become economic opportunities.'],
   ['TVET and vocational skills','tvet','TVET','Skills with purpose, relevant to work and entrepreneurship.','Our approach includes agriculture and agribusiness, poultry farming, entrepreneurship, construction, appropriate technology, repair and maintenance, and other market-oriented vocational skills.'],
   ['Agribusiness and practical farming','agribusiness','Agribusiness','Agriculture as an enterprise and a living classroom.','Through poultry and agribusiness activities, learners can explore production, animal management, marketing, record keeping, customer relations and financial management.'],
   ['Entrepreneurship and enterprise development','entrepreneurship','Entrepreneurship','Start small, build progressively and create value.','Explore business opportunities, business ideas, financial management, record keeping, marketing, business planning, value addition, saving, reinvestment and enterprise management.'],
   ['Youth empowerment','youth-empowerment','Youth empowerment','Transform potential into practical capability.','We encourage young people to become problem solvers, entrepreneurs, skilled workers, farmers, innovators and job creators.']
  ] as [$title,$slug,$category,$summary,$body])\App\Models\Record::for('programs')->newQuery()->firstOrCreate(['slug'=>$slug],compact('title','category','summary','body')+['status'=>'published','created_at'=>now(),'updated_at'=>now()]);
  foreach(['Poultry management','Agribusiness record keeping','Customer service','Business planning','Repair and maintenance','Construction skills'] as $title)\App\Models\Record::for('skills')->newQuery()->firstOrCreate(['title'=>$title],['category'=>'Practical skills','description'=>'Build practical competence through supervised activity and reflective learning.','status'=>'published','created_at'=>now(),'updated_at'=>now()]);
  if(filter_var(env('SEED_DEMO',false),FILTER_VALIDATE_BOOLEAN))$this->demo();
 }
 private function demo(): void {
  $teacher=User::firstOrCreate(['email'=>'instructor@example.test'],['name'=>'Demo Instructor','password'=>Hash::make(env('ADMIN_PASSWORD')),'role'=>'instructor','status'=>'active']);
  $mentor=User::firstOrCreate(['email'=>'mentor@example.test'],['name'=>'Demo Mentor','password'=>Hash::make(env('ADMIN_PASSWORD')),'role'=>'mentor','status'=>'active','expertise'=>'Agribusiness and entrepreneurship','bio'=>'Sample mentor profile for local testing.']);
  $program=DB::table('programs')->where('slug','agribusiness')->value('id');
  $course=DB::table('courses')->where('title','Demo: Poultry enterprise basics')->first();
  $id=$course?->id??DB::table('courses')->insertGetId(['title'=>'Demo: Poultry enterprise basics','program_id'=>$program,'instructor_id'=>$teacher->id,'summary'=>'Sample learning content for testing. Replace and review before launch.','level'=>'Beginner','duration_hours'=>6,'status'=>'published','created_at'=>now(),'updated_at'=>now()]);
  if(!$course)DB::table('course_instructors')->insert(['course_id'=>$id,'user_id'=>$teacher->id,'created_at'=>now(),'updated_at'=>now()]);
  foreach(['Planning your poultry enterprise'=>'Identify your customers, available space, startup costs and daily responsibilities. Write down your assumptions and discuss them with your instructor.','Keeping useful records'=>'Record feed, animal health, sales and expenses each day. Compare total income with costs. This sample lesson is not veterinary advice.'] as $title=>$body)DB::table('lessons')->updateOrInsert(['course_id'=>$id,'title'=>$title],['body'=>$body,'position'=>$title==='Planning your poultry enterprise'?1:2,'status'=>'published','created_at'=>now(),'updated_at'=>now()]);
  DB::table('assignments')->updateOrInsert(['course_id'=>$id,'title'=>'Prepare a simple enterprise plan'],['instructions'=>'Describe your target customers, resources, expected costs, selling price and record-keeping approach.','pass_mark'=>60,'status'=>'published','created_at'=>now(),'updated_at'=>now()]);
 }
}
