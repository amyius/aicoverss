<?php

namespace app\controller;

use app\BaseController;
use app\model\User;
use app\model\Redpacket;
use think\facade\Session;

class Index extends BaseController
{
    public function index()
    {
        $params = $this->request->param();
        $type = isset($params['type']) ? intval($params['type']) : 1;
        $describe = isset($params['describe']) ? trim($params['describe']) : '';
        $list = Redpacket::where('type', $type)->Order('created_at desc');
        if ($params) {
            if ($describe) {
                $list->where('describes', 'like', $describe . '%');
            }
            $list = $list->select();
            return json(['lists' => $list]);
        }
        $list = $list->select();
        $count = Redpacket::count();
        return view('index', ['lists' => $list, 'count' => $count]);
    }


    public function detail()
    {
        $params = $this->request->param();
        $packetid = isset($params['id']) ? trim($params['id']) : 0;
        $detaillist = Redpacket::where('packetid', $packetid)->find();
        $list = Redpacket::Order('created_at desc')->select();
        $username = User::where('id', $detaillist['user_id'])->value('name');
        $detaillist['username'] = $username ? $username : '未知用户';
        return view('detail', ['listdetail' => $detaillist, 'list' => $list]);
    }
    //登录
    public function login()
    {
        $query = $this->request->param();
        if ($this->request->isPost()) {
            if (empty($query['password']) || empty($query['name'])) {
                return json(['code' => 0, 'message' => '参数错误'], 400);
            }
            if ($query['userid']) {
                return json(['code' => 0, 'message' => '您已经登录了，无需重复登录']);
            }
            $user = User::where('name', $query['name'])->find();
            $inputPassword = strtolower($query['password']);
            if ($user && $inputPassword == strtolower($user['password'])) {
                $data = ['id' => $user['id'], 'name' => $user['name']];
                Session::set('userinfo', $data);
                return json(['code' => 1, 'message' => '登录成功', 'data' => $data], 200);
            } else {
                return json(['code' => 0, 'message' => '登录失败，账号或者密码错误', 'data' => 0], 401);
            }
        } else {
            return view('login');
        }
    }
    //注册
    public function register()
    {
        if ($this->request->isPost()) {
            $data = $this->request->post();
            if (empty($data['name']) || empty($data['password'])) {
                return view('register', ['error' => '参数错误']);
            }

            $data['password'] = strtolower($data['password']);
            // $data['password'] = password_hash($data['password'], PASSWORD_DEFAULT);
            $data['created_at'] = date('Y-m-d H:i:s');
            $userModel = new \app\model\User();
            if ($userModel->save($data)) {
                return json(['message' => '注册成功', 'code' => 1]);
            } else {
                return json(['message' => '注册失败，请稍后再试', 'code' => 0]);
            }
        }

        return view('register');
    }

    public function temporary()
    {
        return view('temporary');
    }

    public function forgotpassword()
    {
        if ($this->request->isPost()) {
            $data = $this->request->post();
            if (empty($data['name']) || empty($data['password']) || empty($data['resetpassword'])) {
                return view('forgotpassword', ['error' => '参数错误']);
            }

            $data['password'] = strtolower($data['password']);
            $userModel = new \app\model\User();
            $data['password'] = strtolower($data['password']);
            $userid = $userModel->where('name', $data['name'])->order('created_at desc')->value('id');
            if ($userid) {
                $updatedate = [
                    'password' => $data['password'],
                    'updated_at' => date('Y-m-d H:i:s')
                ];
                $userModel->where('id', $userid)->save($updatedate);
                return json(['message' => '密码重置成功,请登录', 'code' => 1]);
            } else {
                return json(['message' => '密码重置失败，或者该用户暂无账号，请稍后再试', 'code' => 0]);
            }
        }
        return view('forgotpassword');
    }

}
