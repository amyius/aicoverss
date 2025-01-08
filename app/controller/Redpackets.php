<?php

namespace app\controller;

use app\BaseController;

use app\model\Generatedtask;
use app\model\Redpacket;
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

        // 调用腾讯混元模型生成封面红包设计方案
        $result = $this->generateCoverRedPacket($prompt, $user_id);

        // 返回结果
        return json($result);
    }

    private function generateCoverRedPacket($prompt, $user_id)
    {
        try {
            // 实例化一个认证对象，入参需要传入腾讯云账户 SecretId 和 SecretKey，此处还需注意密钥对的保密
            // 代码泄露可能会导致 SecretId 和 SecretKey 泄露，并威胁账号下所有资源的安全性。以下代码示例仅供参考，建议采用更安全的方式来使用密钥，请参见：https://cloud.tencent.com/document/product/1278/85305
            // 密钥可前往官网控制台 https://console.cloud.tencent.com/cam/capi 进行获取
            $SecretId = env('CONFIG.SecretId');
            $SecretKey = env('CONFIG.SecretKey');
            $cred = new Credential($SecretId, $SecretKey);
            // 实例化一个http选项，可选的，没有特殊需求可以跳过
            $httpProfile = new HttpProfile();
            $httpProfile->setEndpoint("hunyuan.tencentcloudapi.com");

            // 实例化一个client选项，可选的，没有特殊需求可以跳过
            $clientProfile = new ClientProfile();
            $clientProfile->setHttpProfile($httpProfile);
            // 实例化要请求产品的client对象,clientProfile是可选的
            $client = new HunyuanClient($cred, "ap-guangzhou", $clientProfile);

            // 实例化一个请求对象,每个接口都会对应一个request对象
            $req = new SubmitHunyuanImageJobRequest();

            $params = array(
                'Prompt' => $prompt,
                'LogoAdd' => 0, //不加水印
                'Resolution' => "768:1280", //分辨率 3:5
            );
            $req->fromJsonString(json_encode($params));

            // 返回的resp是一个SubmitHunyuanImageJobResponse的实例，与请求对象对应
            $resp = $client->SubmitHunyuanImageJob($req);

            // 输出json格式的字符串回包
            $res = json_decode($resp->toJsonString(), true);

            // $data = [
            //     'jobId' => $res['JobId'],
            //     'requestId' => $res['RequestId'],
            //     'created_at' => date('Y-m-d H:i:s', time())
            // ];
            // $sus = Generatedtask::Insert($data);
            if ($res) {
                $imgdata = $this->generateImageRedPacket($res['JobId'], $prompt, $user_id);
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
        }
    }

    private function generateImageRedPacket($jobid, $prompt, $user_id)
    {
        try {
            // 实例化一个认证对象，入参需要传入腾讯云账户 SecretId 和 SecretKey，此处还需注意密钥对的保密
            // 代码泄露可能会导致 SecretId 和 SecretKey 泄露，并威胁账号下所有资源的安全性。以下代码示例仅供参考，建议采用更安全的方式来使用密钥，请参见：https://cloud.tencent.com/document/product/1278/85305 
            // 密钥可前往官网控制台 https://console.cloud.tencent.com/cam/capi  进行获取
            $SecretId = env('CONFIG.SecretId');
            $SecretKey = env('CONFIG.SecretKey');
            $cred = new Credential($SecretId, $SecretKey);
            $httpProfile = new HttpProfile();
            $httpProfile->setEndpoint("hunyuan.tencentcloudapi.com");

            // 实例化一个client选项，可选的，没有特殊需求可以跳过
            $clientProfile = new ClientProfile();
            $clientProfile->setHttpProfile($httpProfile);
            // 实例化要请求产品的client对象,clientProfile是可选的
            $client = new HunyuanClient($cred, "ap-guangzhou", $clientProfile);

            // 实例化一个请求对象,每个接口都会对应一个request对象
            $req = new QueryHunyuanImageJobRequest();

            $params = array(
                'JobId' => $jobid

            );
            $req->fromJsonString(json_encode($params));

            while (true) {
                // 返回的resp是一个QueryHunyuanImageJobResponse的实例，与请求对象对应
                $resp = $client->QueryHunyuanImageJob($req);
                $res = json_decode($resp->toJsonString(), true);

                // 检查JobStatusCode
                $jobStatusCode = $res['JobStatusCode'];
                if ($jobStatusCode == 5) {
                    $imageUrl = $res['ResultImage'][0];
                    $localPath = public_path() . '/image/' . time() . '.png';
                    $relativePath = $this->downloadImageToLocal($imageUrl, $localPath);
                    if ($relativePath) {
                        $data = [
                            'img' => $relativePath,
                            'user_id' => $user_id,
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
}
