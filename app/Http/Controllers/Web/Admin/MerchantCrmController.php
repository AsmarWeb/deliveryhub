<?php
namespace App\Http\Controllers\Web\Admin;
use App\Http\Controllers\Controller;
use App\Models\MerchantLead;
use App\Models\MerchantLeadActivity;
use App\Services\CRM\LeadAiService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
class MerchantCrmController extends Controller {
 private const STATUSES=['new','contacted','interested','negotiating','won','lost'];
 public function index(Request $request){
  $status=$request->query('status');
  $leads=MerchantLead::query()->when(in_array($status,self::STATUSES,true),fn($q)=>$q->where('status',$status))->latest()->paginate(20)->withQueryString();
  $counts=MerchantLead::query()->selectRaw('status, COUNT(*) as total')->groupBy('status')->pluck('total','status');
  return view('admin.merchant-crm.index',compact('leads','counts','status'));
 }
 public function show(MerchantLead $lead){
  $activities=MerchantLeadActivity::where('merchant_lead_id',$lead->id)->with('user')->latest()->get();
  return view('admin.merchant-crm.show',compact('lead','activities'));
 }
 public function update(Request $request,MerchantLead $lead){
  $data=$request->validate(['status'=>['required','in:'.implode(',',self::STATUSES)],'priority'=>['required','integer','between:1,5'],'next_follow_up_at'=>['nullable','date']]);
  DB::transaction(function()use($lead,$data,$request){
   $old=$lead->status;$lead->update($data);
   MerchantLeadActivity::create(['merchant_lead_id'=>$lead->id,'user_id'=>$request->user()->id,'type'=>'update','details'=>'Status: '.$old.' → '.$data['status'].'; priority: '.$data['priority'].'; follow-up: '.($data['next_follow_up_at']??'none')]);
  });
  return back()->with('crm_success','Lead updated.');
 }
 public function note(Request $request,MerchantLead $lead){
  $data=$request->validate(['note'=>['required','string','max:3000']]);
  MerchantLeadActivity::create(['merchant_lead_id'=>$lead->id,'user_id'=>$request->user()->id,'type'=>'note','details'=>$data['note']]);
  return back()->with('crm_success','Note added.');
 }
 public function analyze(Request $request,MerchantLead $lead,LeadAiService $ai){
  try {
   $result=$ai->analyze($lead);
   DB::transaction(function()use($lead,$result,$request){
    $lead->update(['ai_summary'=>$result['summary'],'ai_recommendation'=>$result['recommendation'],'ai_analyzed_at'=>now()]);
    MerchantLeadActivity::create(['merchant_lead_id'=>$lead->id,'user_id'=>$request->user()->id,'type'=>'ai','details'=>'AI analysis generated for review.']);
   });
   return back()->with('crm_success','AI analysis completed.');
  }catch(\Throwable $e){
   Log::warning('CRM AI analysis failed',['lead_id'=>$lead->id,'exception'=>get_class($e)]);
   return back()->withErrors(['ai'=>'AI analysis unavailable. Check server configuration and logs.']);
  }
 }
}
