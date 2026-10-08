<?php
namespace App\Services;

use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\{DB,Validator};

class Earnings
{
    public static function get(User $user, array $input): array
    {
        $filters=Validator::make($input,[
            'enterprise_id'=>'nullable|integer|min:1',
            'period'=>'sometimes|required|in:all,week,month,year,custom',
            'start_date'=>'exclude_unless:period,custom|required|date_format:Y-m-d',
            'end_date'=>'exclude_unless:period,custom|required|date_format:Y-m-d|after_or_equal:start_date',
            'q'=>'nullable|string|max:255',
        ])->validate();
        $filters+=['enterprise_id'=>null,'period'=>'all','start_date'=>null,'end_date'=>null,'q'=>''];
        if($filters['enterprise_id']){
            abort_unless(DB::table('enterprises')->where('user_id',$user->id)
                ->where('id',$filters['enterprise_id'])->exists(),403);
        }
        $today=CarbonImmutable::today();
        [$start,$end]=match($filters['period']){
            'week'=>[$today->startOfWeek(CarbonImmutable::MONDAY),$today->endOfWeek(CarbonImmutable::SUNDAY)],
            'month'=>[$today->startOfMonth(),$today->endOfMonth()],
            'year'=>[$today->startOfYear(),$today->endOfYear()],
            'custom'=>[CarbonImmutable::parse($filters['start_date']),CarbonImmutable::parse($filters['end_date'])],
            default=>[null,null],
        };
        $query=DB::table('transactions')->where('user_id',$user->id);
        if($filters['enterprise_id'])$query->where('enterprise_id',$filters['enterprise_id']);
        if($start)$query->whereBetween('occurred_on',[$start->toDateString(),$end->toDateString()]);
        $income=(clone $query)->where('type','income')->sum('amount');
        $expenses=(clone $query)->where('type','expense')->sum('amount');
        // Description search affects history, never the selected period's totals.
        if($filters['q']!==null&&$filters['q']!==''){
            $search=str_replace(['!','%','_'],['!!','!%','!_'],$filters['q']);
            $query->whereRaw("description LIKE ? ESCAPE '!'",['%'.$search.'%']);
        }
        return ['filters'=>$filters,'income'=>$income,'expenses'=>$expenses,'net'=>$income-$expenses,
            'transactions'=>$query->orderByDesc('occurred_on')->orderByDesc('id')->paginate(20)->withQueryString()];
    }
}
