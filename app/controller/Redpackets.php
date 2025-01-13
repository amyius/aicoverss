<?php

namespace app\controller;

use app\BaseController;
use app\model\Generatedtask;
use app\model\Redpacket;
use app\model\Redpacketlogs;
use TencentCloud\Common\Credential;
use TencentCloud\Common\Profile\ClientProfile;
use TencentCloud\Common\Profile\HttpProfile;
use TencentCloud\Common\Exception\TencentCloudSDKException;
use TencentCloud\Hunyuan\V20230901\HunyuanClient;
use TencentCloud\Hunyuan\V20230901\Models\SubmitHunyuanImageJobRequest;
use TencentCloud\Hunyuan\V20230901\Models\QueryHunyuanImageJobRequest;

class Redpackets extends BaseController
{
    public function redpackter()
    {
        $params = $this->request->param();
        $prompt = isset($params['prompt']) ? trim($params['prompt']) : '';
        $user_id = isset($params['userid']) ? trim($params['userid']) : 0;
        $ip = $this->request->ip(); // 获取请求的IP地址

        $islogin = env('CONFIG.IsLogin');
        if ($islogin && $user_id == 0) {
            return json([
                'code' => 0,
                'msg' => "请先登录",
            ]);
        }

        $limitnumber = env('CONFIG.Limitnumber');
        // 检查用户当天生成红包封面的次数
        // $log = Redpacketlogs::where('user_id', $user_id)
        //     ->where('created_at', date('Y-m-d'))
        //     ->find();
        // if ($log && $log->count >= $limitnumber) {
        //     return json([
        //         'code' => 0,
        //         'msg' => "每个用户一天只能生成{$limitnumber}张红包封面",
        //     ]);
        // }

        // 检查IP当天生成红包封面的次数
        $ipLog = Redpacketlogs::where('ip', $ip)
            ->where('created_at', date('Y-m-d'))
            ->find();
        if ($ipLog && $ipLog->count >= $limitnumber) {
            return json([
                'code' => 0,
                'msg' => "每个IP一天只能生成{$limitnumber}张红包封面",
            ]);
        }

        $result = $this->generateCoverRedPacket($prompt, $user_id, $ip);

        return json($result);
    }

    private function generateCoverRedPacket($prompt, $user_id, $ip)
    {
        try {
            // 实例化一个认证对象，入参需要传入腾讯云账户 SecretId 和 SecretKey，此处还需注意密钥对的保密
            $SecretId = env('CONFIG.SecretId');
            $SecretKey = env('CONFIG.SecretKey');
            $cred = new Credential($SecretId, $SecretKey);
            $httpProfile = new HttpProfile();
            $httpProfile->setEndpoint("hunyuan.tencentcloudapi.com");

            $clientProfile = new ClientProfile();
            $clientProfile->setHttpProfile($httpProfile);
            $client = new HunyuanClient($cred, "ap-guangzhou", $clientProfile);

            $req = new SubmitHunyuanImageJobRequest();

            $params = array(
                'Prompt' => $prompt,
                'LogoAdd' => 0, //不加水印
                'Resolution' => "768:1280", //分辨率 3:5
            );
            $req->fromJsonString(json_encode($params));

            $resp = $client->SubmitHunyuanImageJob($req);
            $res = json_decode($resp->toJsonString(), true);

            if ($res) {
                // 记录用户生成红包封面的次数
                // $this->recordUserRedpacketLog($user_id);
                // 记录IP生成红包封面的次数
                $this->recordIpRedpacketLog($ip);

                $imgdata = $this->generateImageRedPacket($res['JobId'], $prompt, $user_id, $ip);
                return [
                    'code' => 1,
                    'message' => '成功',
                    'img' => $imgdata['img'],
                    'insertedId' => $imgdata['insertedId']
                ];
            } else {
                return [
                    'code' => 0,
                    'message' => '失败',
                    'data' => json_decode($resp->toJsonString(), true)
                ];
            }
        } catch (TencentCloudSDKException $e) {
            error_log($e->getMessage());
            return [
                'code' => 0,
                'message' => '系统错误',
            ];
        }
    }

    private function generateImageRedPacket($jobid, $prompt, $user_id, $ip)
    {
        try {
            $SecretId = env('CONFIG.SecretId');
            $SecretKey = env('CONFIG.SecretKey');
            $cred = new Credential($SecretId, $SecretKey);
            $httpProfile = new HttpProfile();
            $httpProfile->setEndpoint("hunyuan.tencentcloudapi.com");

            $clientProfile = new ClientProfile();
            $clientProfile->setHttpProfile($httpProfile);
            $client = new HunyuanClient($cred, "ap-guangzhou", $clientProfile);

            $req = new QueryHunyuanImageJobRequest();

            $params = array(
                'JobId' => $jobid
            );
            $req->fromJsonString(json_encode($params));

            while (true) {
                $resp = $client->QueryHunyuanImageJob($req);
                $res = json_decode($resp->toJsonString(), true);

                $jobStatusCode = $res['JobStatusCode'];
                if ($jobStatusCode == 5) {
                    $imageUrl = $res['ResultImage'][0];
                    $localPath = public_path() . '/image/' . time() . '.png';
                    $relativePath = $this->downloadImageToLocal($imageUrl, $localPath);
                    if ($relativePath) {
                        $data = [
                            'img' => $relativePath,
                            'user_id' => $user_id,
                            'ip' => $ip,
                            'describes' => $res['RevisedPrompt'][0],
                            'meaning' => $prompt,
                            'created_at' => date('Y-m-d H:i:s')
                        ];
                        $redpacket = new \app\model\Redpacket();

                        $sus = $redpacket->save($data);
                        if ($sus) {
                            $insertedId = $redpacket->id;
                            return [
                                'img' => $relativePath,
                                'insertedId' => $insertedId
                            ];
                            break;
                        }
                    }
                }
                sleep(1);
            }
        } catch (TencentCloudSDKException $e) {
            error_log($e->getMessage());
            return [
                'code' => 0,
                'message' => '系统错误',
            ];
        }
    }

    private function downloadImageToLocal($imageUrl, $localPath)
    {
        $imageContent = file_get_contents($imageUrl);

        if ($imageContent === false) {
            error_log("Failed to get image content from URL: " . $imageUrl);
            return false;
        }

        $result = file_put_contents($localPath, $imageContent);

        if ($result === false) {
            error_log("Failed to write image to local path: " . $localPath);
            return false;
        }

        $relativePath = str_replace(public_path(), '', $localPath);
        return $relativePath;
    }

    //记录每个用户生成红包封面的次数
    // private function recordUserRedpacketLog($user_id)
    // {
    //     $log = Redpacketlogs::where('user_id', $user_id)
    //         ->where('created_at', date('Y-m-d'))
    //         ->find();
    //     if ($log) {
    //         $log->count += 1;
    //         $log->save();
    //     } else {
    //         $log = new Redpacketlogs();
    //         $log->user_id = $user_id;
    //         $log->created_at = date('Y-m-d');
    //         $log->count = 1;
    //         $log->save();
    //     }
    // }

    private function recordIpRedpacketLog($ip)
    {
        $ipLog = Redpacketlogs::where('ip', $ip)
            ->where('created_at', date('Y-m-d'))
            ->find();
        if ($ipLog) {
            $ipLog->count += 1;
            $ipLog->save();
        } else {
            $ipLog = new Redpacketlogs();
            $ipLog->ip = $ip;
            $ipLog->created_at = date('Y-m-d');
            $ipLog->count = 1;
            $ipLog->save();
        }
    }
}
