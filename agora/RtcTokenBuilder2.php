<?php

require_once __DIR__ . "/Util.php";
require_once __DIR__ . "/AccessToken2.php";

class RtcTokenBuilder2
{
    const ROLE_PUBLISHER  = 1;
    const ROLE_SUBSCRIBER = 2;

    public static function buildTokenWithUid($appId, $appCertificate, $channelName, $uid, $role, $tokenExpire, $privilegeExpire = 0)
    {
        return self::buildTokenWithUserAccount($appId, $appCertificate, $channelName, $uid, $role, $tokenExpire, $privilegeExpire);
    }

    public static function buildTokenWithUserAccount($appId, $appCertificate, $channelName, $account, $role, $tokenExpire, $privilegeExpire = 0)
    {
        $token      = new AccessToken2($appId, $appCertificate, $tokenExpire);
        $serviceRtc = new ServiceRtc($channelName, is_int($account) ? (string)$account : $account);

        $serviceRtc->addPrivilege($serviceRtc::PRIVILEGE_JOIN_CHANNEL, $privilegeExpire);
        if ($role == self::ROLE_PUBLISHER) {
            $serviceRtc->addPrivilege($serviceRtc::PRIVILEGE_PUBLISH_AUDIO_STREAM, $privilegeExpire);
            $serviceRtc->addPrivilege($serviceRtc::PRIVILEGE_PUBLISH_VIDEO_STREAM, $privilegeExpire);
            $serviceRtc->addPrivilege($serviceRtc::PRIVILEGE_PUBLISH_DATA_STREAM,  $privilegeExpire);
        }
        $token->addService($serviceRtc);

        return $token->build();
    }
}
