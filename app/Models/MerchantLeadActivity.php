<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class MerchantLeadActivity extends Model {
 protected $fillable=['merchant_lead_id','user_id','type','details'];
 public function lead(){return $this->belongsTo(MerchantLead::class,'merchant_lead_id');}
 public function user(){return $this->belongsTo(User::class);}
}
