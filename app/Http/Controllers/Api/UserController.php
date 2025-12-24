<?php

namespace App\Http\Controllers\Api; 

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use App\Models\WpUser;
use Carbon\Carbon;
use App\Models\MembershipWallet;
use App\Models\UserReferral;
use App\Models\WpUsermeta;
use App\Models\MembershipWalletTransaction;

class UserController extends Controller
{
   public function postUser(Request $request) {
      $item = $request->all();
      $email = $item['email'];
      // Lấy local part trước @
      $parts = explode('@', $email);
      $localPart = $parts[0];
      
      // Bỏ phần sau dấu +
      $user_login = explode('+', $localPart)[0];
      
      // Xử lý created_at (có thể là MongoDB $date hoặc string thường)
      $createdAt = $item['created_at']['$date'] ?? $item['created_at'];
      $date = new \DateTime($createdAt);
      $formattedDate = $date->format('Y-m-d H:i:s');
      
      // Check admin
      $isAdmin = ($item['user_type_id'] === 1);
      
      // Tìm position tương ứng
      $positionItem = null;
      $positions = $this->getPosition();
      foreach ($positions as $pos) {
          if (($pos['_id']['$oid'] ?? null) === $item['position_id']) {
              $positionItem = $pos;
              break;
          }
      }
      
      // Lấy percent
      $percent = $positionItem ? $positionItem['percent'] : 0;
      // Thời gian hiện tại
      $now = new \DateTime();
    
      if (!empty($item['password'])) {

         $existingUser = WpUser::where('user_email', $item['email'])->first();
         if (!$existingUser) {
           
            $user = WpUser::create([
                 'user_login'      => $user_login,
                 'user_pass'       => $item['password'], // ⚠ nếu là WordPress chuẩn → cần wp_hash_password
                 'user_nicename'   => $item['name'] ?? $user_login,
                 'user_email'      => $item['email'],
                 'user_url'        => '',
                 'user_registered' => $formattedDate,
                 'user_status'     => 0,
                 'display_name'    => $item['name'] ?? $user_login,
             ]);

             $usermetas = $this->settingUserMeta($item, $user->ID, $isAdmin, $percent);
             WpUsermeta::insert(
                array_map(fn ($meta) => $meta->toArray(), $usermetas)
             );

             if ($positionItem) {

               switch ($positionItem['name']) {
                   case 'ĐẠI SỨ':
                       $planId = 1;
                       break;
                   case 'ĐẠI SỨ ĐỒNG':
                       $planId = 2;
                       break;
                   case 'ĐẠI SỨ BẠC':
                       $planId = 3;
                       break;
                   case 'ĐẠI SỨ VÀNG':
                       $planId = 4;
                       break;
                   case 'ĐẠI SỨ KIM CƯƠNG':
                       $planId = 5;
                       break;
                   default:
                       $planId = 1;
               }
           
               DB::transaction(function () use ($user, $planId) {
           
                  $now = Carbon::now();
           
                   MembershipWallet::create([
                       'user_id'             => $user->ID,
                       'membership_plan_id'  => $planId,
                       'activated_at'        => $now,
                       'status'              => 'active',
                       'expired_at'         => $now->copy()->addYear(),
                   ]);
           
                   MembershipWalletTransaction::create([
                       'user_id'             => $user->ID,
                       'membership_plan_id'  => $planId,
                       'transaction_type'    => 'purchase',
                       'transaction_date'    => now(),
                   ]);
                  
               });
               $this->referralsUser($item);
           }
         }
         
     }
      
      return response()->json(['success' => true]);
   }

   public function referralsUser(array $item): array
   {
      // Điều kiện giống code gốc
      if (empty($item['coupon_parent']) || empty($item['password'])) {
         return ['message' => 'User không có coupon_parent hoặc password'];
      }

      // Tìm user hiện tại theo user_code
      $userMeta = WpUsermeta::where('meta_key', 'user_code')
         ->where('meta_value', $item['coupon'])
         ->first();

      if (!$userMeta) {
         return ['message' => 'Không tìm thấy user hiện tại'];
      }

      // Tìm user hiện tại theo user_code
      $userMetaParent = WpUsermeta::where('meta_key', 'user_code')
         ->where('meta_value', $item['coupon_parent'])
         ->first();

      if (!$userMetaParent) {
         return ['message' => 'Không tìm thấy user hiện tại'];
      }
      
      $userReferral = UserReferral::where('user_id', $userMeta->user_id)->where('parent_id', $userMetaParent->user_id)->where('level', 1)->first();
      if(empty($userReferral)) {
         UserReferral::create([
            'user_id'             => $userMeta->user_id,
            'parent_id'  => $userMetaParent->user_id,
            'level'        => 1,
            'created_at'  => date('Y-m-d H:i:s')
         ]);
      }
      $checkLevel2 = UserReferral::where('user_id', $userMetaParent->user_id)->where('level', 1)->first();
      if(!empty($checkLevel2)) {
         $userReferral = UserReferral::where('user_id', $userMeta->user_id)->where('parent_id', $checkLevel2->user_id)->where('level', 2)->first();
         if(empty($userReferral)) {
            UserReferral::create([
               'user_id'    => $userMeta->user_id,
               'parent_id'  => $checkLevel2->user_id,
               'level'        => 2,
               'created_at'  => date('Y-m-d H:i:s')
            ]);
         }
      }

      return [
         'message' => 'Đã xử lý',
      ];
   }

   function settingUserMeta($item,$userId, $isAdmin = false, $percent = 0)
   {
      $newUsermeta = [];

      $newUsermeta[] = new WpUsermeta([
         'user_id' => $userId,
         'meta_key' => 'nickname',
         'meta_value' => $item['name'] ?? '',
      ]);

      $newUsermeta[] = new WpUsermeta([
         'user_id' => $userId,
         'meta_key' => 'first_name',
         'meta_value' => '',
      ]);

      $newUsermeta[] = new WpUsermeta([
         'user_id' => $userId,
         'meta_key' => 'last_name',
         'meta_value' => '',
      ]);

      $newUsermeta[] = new WpUsermeta([
         'user_id' => $userId,
         'meta_key' => 'description',
         'meta_value' => '',
      ]);

      $newUsermeta[] = new WpUsermeta([
         'user_id' => $userId,
         'meta_key' => 'rich_editing',
         'meta_value' => 'true',
      ]);

      $newUsermeta[] = new WpUsermeta([
         'user_id' => $userId,
         'meta_key' => 'syntax_highlighting',
         'meta_value' => 'true',
      ]);

      $newUsermeta[] = new WpUsermeta([
         'user_id' => $userId,
         'meta_key' => 'comment_shortcuts',
         'meta_value' => 'false',
      ]);

      $newUsermeta[] = new WpUsermeta([
         'user_id' => $userId,
         'meta_key' => 'admin_color',
         'meta_value' => 'fresh',
      ]);

      $newUsermeta[] = new WpUsermeta([
         'user_id' => $userId,
         'meta_key' => 'use_ssl',
         'meta_value' => '0',
      ]);

      $newUsermeta[] = new WpUsermeta([
         'user_id' => $userId,
         'meta_key' => 'locale',
         'meta_value' => '',
      ]);

      $newUsermeta[] = new WpUsermeta([
         'user_id' => $userId,
         'meta_key' => 'wp_capabilities',
         'meta_value' => $isAdmin
               ? 'a:2:{s:13:"administrator";b:1;s:24:"dokan_wholesale_customer";b:1;}'
               : 'a:1:{s:8:"customer";b:1;}',
      ]);

      $newUsermeta[] = new WpUsermeta([
         'user_id' => $userId,
         'meta_key' => 'wp_user_level',
         'meta_value' => '0',
      ]);

      $newUsermeta[] = new WpUsermeta([
         'user_id' => $userId,
         'meta_key' => 'user_code',
         'meta_value' => $item['coupon'] ?? '',
      ]);

      $newUsermeta[] = new WpUsermeta([
         'user_id' => $userId,
         'meta_key' => 'tags',
         'meta_value' => '["new-customer"]',
      ]);

      $commission = [
         'direct' => $percent,
         'indirect' => [
               'levels' => [
                  ['level' => 1, 'rate' => 5],
                  ['level' => 2, 'rate' => $percent],
               ],
         ],
      ];

      $newUsermeta[] = new WpUsermeta([
         'user_id' => $userId,
         'meta_key' => 'commission_multiple_level',
         'meta_value' => serialize($commission),
      ]);

      $createdAt = $item['created_at']['$date'] ?? $item['created_at'] ?? null;
      $customerSince = $createdAt
         ? Carbon::parse($createdAt)->format('Y-m-d')
         : Carbon::now()->format('Y-m-d');

      $newUsermeta[] = new WpUsermeta([
         'user_id' => $userId,
         'meta_key' => 'customer_since',
         'meta_value' => $customerSince,
      ]);

      $newUsermeta[] = new WpUsermeta([
         'user_id' => $userId,
         'meta_key' => 'loyalty_points',
         'meta_value' => '0',
      ]);

      $newUsermeta[] = new WpUsermeta([
         'user_id' => $userId,
         'meta_key' => 'billing_phone',
         'meta_value' => $item['tel'] ?? '',
      ]);

      $newUsermeta[] = new WpUsermeta([
         'user_id' => $userId,
         'meta_key' => 'billing_email',
         'meta_value' => $item['email'] ?? '',
      ]);

      $newUsermeta[] = new WpUsermeta([
         'user_id' => $userId,
         'meta_key' => 'ref_code',
         'meta_value' => $item['coupon_parent'] ?? '',
      ]);

      $newUsermeta[] = new WpUsermeta([
         'user_id' => $userId,
         'meta_key' => 'show_admin_bar_front',
         'meta_value' => 'true',
      ]);

      $newUsermeta[] = new WpUsermeta([
         'user_id' => $userId,
         'meta_key' => 'ref_code_processed',
         'meta_value' => '1',
      ]);

      $newUsermeta[] = new WpUsermeta([
         'user_id' => $userId,
         'meta_key' => '_referral_api_called',
         'meta_value' => '1',
      ]);

      $newUsermeta[] = new WpUsermeta([
         'user_id' => $userId,
         'meta_key' => '_user_meta_processed',
         'meta_value' => '1',
      ]);

      $newUsermeta[] = new WpUsermeta([
         'user_id' => $userId,
         'meta_key' => '_wc_order_attribution_source_type',
         'meta_value' => 'typein',
      ]);

      $newUsermeta[] = new WpUsermeta([
         'user_id' => $userId,
         'meta_key' => '_wc_order_attribution_utm_source',
         'meta_value' => '(direct)',
      ]);

      $newUsermeta[] = new WpUsermeta([
         'user_id' => $userId,
         'meta_key' => '_dokan_email_pending_verification',
         'meta_value' => '1',
      ]);

      $newUsermeta[] = new WpUsermeta([
         'user_id' => $userId,
         'meta_key' => 'tax_code',
         'meta_value' => $item['tax_code'] ?? '',
      ]);

      $newUsermeta[] = new WpUsermeta([
         'user_id' => $userId,
         'meta_key' => 'email_tax',
         'meta_value' => $item['email_tax'] ?? '',
      ]);

      $newUsermeta[] = new WpUsermeta([
         'user_id' => $userId,
         'meta_key' => 'number_cccd',
         'meta_value' => $item['number_cccd'] ?? '',
      ]);

      $newUsermeta[] = new WpUsermeta([
         'user_id' => $userId,
         'meta_key' => 'date_cccd',
         'meta_value' => $item['date_cccd'] ?? '',
      ]);

      return $newUsermeta;
   }

   public function getPosition() {
      return [
         [
             "_id" => [
                 "\$oid" => "67c2bb2531291539610f938b"
             ],
             "name" => "ĐẠI SỨ BẠC",
             "status" => "on",
             "price" => 85714286,
             "level" => 1,
             "to_price" => 0,
             "vuot_cap" => 0,
             "percent" => 30,
             "thuong_cap_bac" => 0,
             "note" => null,
             "type" => "dau_tu",
             "von_nhap" => "60000000",
             "updated_at" => [
                 "\$date" => "2025-10-20T03:27:38.806Z"
             ],
             "created_at" => [
                 "\$date" => "2025-03-01T07:45:41.000Z"
             ],
             "percent_ds" => 0,
             "percent_f1" => 0,
             "img" => "daisubac.jpg",
             "view" => 851
         ],
         [
             "_id" => [
                 "\$oid" => "67c2bb3a32d499ebf105321a"
             ],
             "name" => "ĐẠI SỨ VÀNG",
             "status" => "on",
             "price" => 149253731,
             "level" => 2,
             "to_price" => 0,
             "vuot_cap" => 0,
             "percent" => 33,
             "thuong_cap_bac" => 0,
             "note" => null,
             "type" => "dau_tu",
             "von_nhap" => "100000000",
             "updated_at" => [
                 "\$date" => "2025-10-19T12:13:22.038Z"
             ],
             "created_at" => [
                 "\$date" => "2025-03-01T07:46:02.000Z"
             ],
             "percent_ds" => 0,
             "percent_f1" => 0,
             "img" => "daisuvang-1.jpg",
             "view" => 229
         ],
         [
             "_id" => [
                 "\$oid" => "67c2bb5732d499ebf105321b"
             ],
             "name" => "ĐẠI SỨ KIM CƯƠNG",
             "status" => "on",
             "price" => 307692203,
             "level" => 3,
             "to_price" => 0,
             "vuot_cap" => 0,
             "percent" => 35,
             "thuong_cap_bac" => 0,
             "note" => null,
             "type" => "dau_tu",
             "von_nhap" => "200000000",
             "updated_at" => [
                 "\$date" => "2025-10-20T04:46:12.978Z"
             ],
             "created_at" => [
                 "\$date" => "2025-03-01T07:46:31.000Z"
             ],
             "percent_ds" => 0,
             "percent_f1" => 0,
             "img" => "daisukimcuong-1.jpg",
             "view" => 275
         ],
         [
             "_id" => [
                 "\$oid" => "68b02d460ff1320e704756c2"
             ],
             "name" => "ĐẠI SỨ",
             "status" => "on",
             "price" => 500000,
             "level" => 0,
             "to_price" => 0,
             "vuot_cap" => 0,
             "percent" => 15,
             "percent_f1" => 0,
             "percent_ds" => 0,
             "thuong_cap_bac" => 0,
             "note" => null,
             "type" => "dau_tu",
             "von_nhap" => "500000",
             "updated_at" => [
                 "\$date" => "2025-10-20T06:26:44.182Z"
             ],
             "created_at" => [
                 "\$date" => "2025-08-28T10:19:50.181Z"
             ],
             "img" => "Daisu-10.jpg",
             "view" => 3740
         ],
         [
             "_id" => [
                 "\$oid" => "68c20be9e593f814731b7273"
             ],
             "name" => "ĐẠI SỨ ĐỒNG",
             "status" => "on",
             "price" => 38461538,
             "level" => 0,
             "to_price" => 0,
             "vuot_cap" => 0,
             "percent" => 22,
             "percent_f1" => 0,
             "percent_ds" => 0,
             "thuong_cap_bac" => 0,
             "note" => null,
             "type" => "dau_tu",
             "von_nhap" => "30000000",
             "updated_at" => [
                 "\$date" => "2025-10-20T02:56:30.657Z"
             ],
             "created_at" => [
                 "\$date" => "2025-09-10T23:38:17.962Z"
             ],
             "img" => "daisudong.jpg",
             "view" => 346
         ]
     ];
   }
}   
