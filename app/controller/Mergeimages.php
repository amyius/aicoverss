<?php

namespace app\controller;

use app\BaseController;

class Mergeimages extends BaseController
{
    public function mergeimg()
    {
        $params = $this->request->param();
        $image1 = isset($params['image1']) ? trim($params['image1']) : '';
        $image2 = isset($params['image2']) ? trim($params['image2']) : '';
        header('Content-Type: image/png');
        $image1_path = public_path() . $image1;
        $image2_path = public_path() . $image2;

        $image1 = imagecreatefrompng($image1_path);
        $image2 = imagecreatefrompng($image2_path);

        $image1_width = imagesx($image1);
        $image1_height = imagesy($image1);
        $image2_width = imagesx($image2);
        $image2_height = imagesy($image2);

        $merged_image = imagecreatetruecolor($image1_width, $image1_height - 20);

        imagecopy($merged_image, $image1, 0, 0, 0, 0, $image1_width, $image1_height);

        $cover_x = 0;
        $cover_y = 900;
        $cover_width = min($image2_width, 1500);
        $cover_height = min($image2_height, 1000);

        imagecopy($merged_image, $image2, $cover_x, $cover_y, 0, 0, $cover_width, $cover_height);

        $output_path = '/image/' . time() . '.png';
        imagepng($merged_image, public_path() . $output_path);
        $output_path = $this->request->domain() . $output_path;

        // 释放内存
        imagedestroy($image1);
        imagedestroy($image2);
        imagedestroy($merged_image);

        return json(['code' => 1, 'message' => '图片合并成功', 'data' => $output_path]);
    }
}
