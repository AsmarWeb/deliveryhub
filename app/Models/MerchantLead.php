<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MerchantLead extends Model
{
    protected $fillable = ['company_name','contact_name','email','phone','emirate','business_type','monthly_deliveries','message','preferred_language','status','source'];
}
