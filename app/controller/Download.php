<?php

namespace app\controller;

use app\BaseController;

class Download extends BaseController
{
    public function index()
    {
        $imageSrc = $this->request->param('imageSrc');
        if (empty($imageSrc)) {
            return json(['error' => 'Image source is required'], 400);
        }
        $imageSrc = public_path() . urldecode($imageSrc);
        // 获取图片内容
        $imageContent = file_get_contents($imageSrc);
        if ($imageContent === false) {
            return json(['error' => 'Failed to fetch image'], 500);
        }

        // 设置响应头，触发浏览器下载行为
        $filename = 'image.jpg';
        $response = response($imageContent)
            ->contentType('image/jpeg')
            ->header([
                'Content-Disposition' => 'attachment; filename="' . basename($filename) . '"',
                'Content-Type' => 'image/jpeg',
                'Content-Length' => strlen($imageContent),
                'Cache-Control' => 'no-cache, no-store, must-revalidate',
                'Pragma' => 'no-cache',
                'Expires' => '0',
            ]);

        return $response;
    }
}
