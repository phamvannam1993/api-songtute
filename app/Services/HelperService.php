<?php

namespace App\Services;

class HelperService
{
	public function createSlug($str) {
		$str = trim(mb_strtolower($str));
		$str = preg_replace('/(à|á|ạ|ả|ã|â|ầ|ấ|ậ|ẩ|ẫ|ă|ằ|ắ|ặ|ẳ|ẵ)/', 'a', $str);
		$str = preg_replace('/(è|é|ẹ|ẻ|ẽ|ê|ề|ế|ệ|ể|ễ)/', 'e', $str);
		$str = preg_replace('/(ì|í|ị|ỉ|ĩ)/', 'i', $str);
		$str = preg_replace('/(ò|ó|ọ|ỏ|õ|ô|ồ|ố|ộ|ổ|ỗ|ơ|ờ|ớ|ợ|ở|ỡ)/', 'o', $str);
		$str = preg_replace('/(ù|ú|ụ|ủ|ũ|ư|ừ|ứ|ự|ử|ữ)/', 'u', $str);
		$str = preg_replace('/(ỳ|ý|ỵ|ỷ|ỹ)/', 'y', $str);
		$str = preg_replace('/(đ)/', 'd', $str);
		$str = preg_replace('/[^a-z0-9-\s]/', '', $str);
		$str = preg_replace('/([\s]+)/', '-', $str);
	return $str;
	}
   
	public function uploadImage($base64Image, $oldImage) {
		 // Tách định dạng và dữ liệu
		 list($type, $dataImage) = explode(';', $base64Image);
		 list(, $dataImage) = explode(',', $dataImage);
	 
		 $dataImage = base64_decode($dataImage);
	 
		 // Xác định phần mở rộng ảnh từ định dạng MIME
		 $ext = '';
		 if (strpos($type, 'image/jpeg') !== false) {
			 $ext = 'jpg';
		 } elseif (strpos($type, 'image/png') !== false) {
			 $ext = 'png';
		 } elseif (strpos($type, 'image/gif') !== false) {
			 $ext = 'gif';
		 }
	 
		 // Tạo tên file duy nhất
		 $filename = uniqid() . '.' . $ext;
	 
		 // Đường dẫn lưu ảnh trong thư mục public/assets/uploads/images
		 $filepath = public_path('assets/uploads/images/' . $filename);
	 
		 // Lưu ảnh vào thư mục public
		 file_put_contents($filepath, $dataImage);
		 
		 // Lưu đường dẫn ảnh vào database hoặc một biến khác
		 $path = 'assets/uploads/images/' . $filename;
	 
		 // Kiểm tra nếu có ảnh cũ và xóa
		 if ($oldImage) {
			 $oldImagePath = public_path($oldImage);
			 if (file_exists($oldImagePath)) {
				 unlink($oldImagePath);
			 }
		 }
		 return $path;
	}
}
