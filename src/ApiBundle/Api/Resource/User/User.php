<?php

namespace ApiBundle\Api\Resource\User;

use ApiBundle\Api\Annotation\ApiConf;
use ApiBundle\Api\Annotation\ResponseFilter;
use ApiBundle\Api\ApiRequest;
use ApiBundle\Api\Resource\AbstractResource;
use Biz\Common\BizSms;
use AppBundle\Common\ArrayToolkit;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use ApiBundle\Api\Exception\ErrorCode;
use Biz\User\UserException;
use AppBundle\Common\EncryptionToolkit;
use AppBundle\Common\MathToolkit;
use AppBundle\Common\DeviceToolkit;
use Biz\System\SettingException;
use AppBundle\Common\SmsToolkit;
use ApiBundle\Api\Util\AssetHelper;

class User extends AbstractResource
{
    /**
     * @ApiConf(isRequiredAuth=false)
     * @ResponseFilter(class="ApiBundle\Api\Resource\User\UserFilter", mode="simple")
     */
    public function get(ApiRequest $request, $identify)
    {
        $identifyType = $request->query->get('identifyType', 'id');

        $user = null;
        switch ($identifyType) {
            case 'id':
                $user = $this->getUserService()->getUser($identify);
                break;
            case 'email':
                $user = $this->getUserService()->getUserByEmail($identify);
                break;
            case 'mobile':
                $user = $this->getUserService()->getUserByVerifiedMobile($identify);
                break;
            case 'nickname':
                $user = $this->getUserService()->getUserByNickname($identify);
                break;
            case 'token':
                $user = $this->getUserService()->getUserByUUID($identify);
                break;
            default:
                break;
        }

        return $user;
    }

    /**
     * @ApiConf(isRequiredAuth=false)
     */
    public function add(ApiRequest $request)
    {
        // 目前只支持手机注册
        $auth = $this->getSettingService()->get('auth', array());
        if (!(isset($auth['register_mode']) && in_array($auth['register_mode'], array('mobile', 'email_or_mobile')))) {
            throw SettingException::FORBIDDEN_MOBILE_REGISTER();
        }

        //校验云短信开启
        $smsSetting = $this->getSettingService()->get('cloud_sms', array());
        if (empty($smsSetting['sms_enabled'])) {
            throw SettingException::FORBIDDEN_SMS_SEND();
        }

        //校验字段缺失
        $fields = $request->request->all();
        if (!ArrayToolkit::requireds($fields, array(
            'mobile',
            'smsToken',
            'smsCode',
            'encrypt_password',
        ), true)) {
            throw new BadRequestHttpException('Incorrect indicator', null, ErrorCode::INVALID_ARGUMENT);
        }

        //校验验证码,基于token，默认10次机会
        $status = $this->getBizSms()->check(BizSms::SMS_BIND_TYPE, $fields['mobile'], $fields['smsToken'], $fields['smsCode']);
        if ($status != BizSms::STATUS_SUCCESS) {
            throw UserException::FORBIDDEN_SEND_MESSAGE();
        }

        $nickname = MathToolkit::uniqid();
        while (!$this->getUserService()->isNicknameAvaliable($nickname)) {
            $nickname =  MathToolkit::uniqid();
        }

        $registeredWay =  DeviceToolkit::getMobileDeviceType($request->headers->get('user-agent'));
        $user = $this->controller->getAuthService()->register(array(
            'mobile' => $fields['mobile'],
            'nickname' => $nickname,
            'password' => $this->getPassword($request),
            'registeredWay' => $registeredWay,
            'createdIp' => $request->getClientIp(),
        ));

        $token = $this->getUserService()->makeToken('mobile_login', $user['id'], time() + 3600 * 24 * 30);
        $user = $this->filterUser($user);

        return array(
            'user' => $user,
            'token' => $token,
        );
    }

    private function filterUser($user)
    {
        return ArrayToolkit::parts($user, array(
            'id',
            'email',
            'locale',
            'uri',
            'nickname',
            'title',
            'type',
            'smallAvatar',
            'mediumAvatar',
            'largeAvatar',
            'roles',
            'locked',
        ));

        $user['smallAvatar'] = AssetHelper::getFurl($user['smallAvatar'], 'avatar.png');
        $user['mediumAvatar'] = AssetHelper::getFurl($user['mediumAvatar'], 'avatar.png');
        $user['largeAvatar'] = AssetHelper::getFurl($user['largeAvatar'], 'avatar.png');

        return $user;
    }

    private function getPassword($request)
    {
        return EncryptionToolkit::XXTEADecrypt(base64_decode($request->request->get('password')), $request->getHost());
    }

    /**
     * @return \Biz\User\Service\UserService
     */
    private function getUserService()
    {
        return $this->service('User:UserService');
    }

    private function getSettingService()
    {
        return $this->service('System:SettingService');
    }

    protected function getBizSms()
    {
        $biz = $this->getBiz();

        return $biz['biz_sms'];
    }
}
