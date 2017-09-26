<?php

namespace app\models;


use app\models\base\BaseEchoUserBehavior;
use app\library\Util;
use app\library\utils\StringUtil;
use yii;

class EchoUserBehavior extends BaseEchoUserBehavior
{
    public static $ARConfig = ['table_name' => 'echo_user_behavior_new'];

    const OBJ_TYPE_SOUND = 1;
    const OBJ_TYPE_EXPRESS = 2;
    const OBJ_TYPE_MV = 3;
    const OBJ_TYPE_CHANNEL = 4;
    const OBJ_TYPE_EVENT = 5;
    const OBJ_TYPE_EVENT_CONTENT = 6;
    const OBJ_TYPE_USER = 7;
    const OBJ_TYPE_TV_CHANNEL = 8;
    const OBJ_TYPE_TOPIC = 9;
    const OBJ_TYPE_GAME_SHARE = 10;
    const OBJ_TYPE_GAME_SOUND = 11;
    const OBJ_TYPE_ECHO_GROUP = 12;

    public static $objTypeMap = [
        self::OBJ_TYPE_SOUND => EchoUserActivity::FIELD_SOUND_ID,
        self::OBJ_TYPE_EXPRESS => EchoUserActivity::FIELD_EXPRESS_ID,
        self::OBJ_TYPE_MV => EchoUserActivity::FIELD_MV_ID,
        self::OBJ_TYPE_CHANNEL => EchoUserActivity::FIELD_CHANNEL_ID,
        self::OBJ_TYPE_EVENT => EchoUserActivity::FIELD_EVENT_ID,
        self::OBJ_TYPE_EVENT_CONTENT => EchoUserActivity::FIELD_EVENT_CONTENT_ID,
        self::OBJ_TYPE_USER => EchoUserActivity::FIELD_FOLLOW_USER_ID,
        self::OBJ_TYPE_TV_CHANNEL => EchoUserActivity::FIELD_TV_CHANNEL_ID,
        self::OBJ_TYPE_TOPIC => EchoUserActivity::FIELD_TOPIC_ID,
        self::OBJ_TYPE_GAME_SHARE => EchoUserActivity::FIELD_GAME_SHARE_ID,
        self::OBJ_TYPE_GAME_SOUND => EchoUserActivity::FIELD_GAME_SOUND_ID,
        self::OBJ_TYPE_ECHO_GROUP => EchoUserActivity::FIELD_ECHO_GROUP_ID,
    ];

    const SHARE_LUCKY = 1;

    const DEVICE_WEB = 1;
    const DEVICE_ANDROID = 2;
    const DEVICE_IOS = 3;
    const DEVICE_WINDOWS = 4;

    public static function create(
        $activityId,
        $userId,
        $type,
        $objType,
        $objId,
        $objExtId,
        $imageCount,
        $inputContent,
        $sourceActivityId,
        $originActivityId,
        $createTime = 0,
        $share = 0,
        $language = 0,
        $relayCount = 0,
        $commentCount = 0,
        $likeCount = 0
    )
    {
        //$parseTopic = YII_ENV == 'prod' && YII_FRONTEND == 'app' && !Util::isNewVersion(2016121601, 140) ? false : true;
        $parseTopic = true;

        $obj = self::key($activityId);
        $obj->activity_id = $activityId;
        $obj->user_id = $userId;
        $obj->type = $type;
        $obj->obj_type = $objType;
        $obj->obj_id = $objId;
        $obj->obj_ext_id = $objExtId;
        $obj->image_count = $imageCount;
        $obj->input_content = $inputContent ? : '';
        $obj->input_content_sign = $inputContent ? md5($inputContent) : '';
        $info = StringUtil::parseActivityContent($inputContent, $obj, EchoUser::atUserHandler($obj), null, EchoUserActivityTopic::atTopicHandler($obj), $parseTopic);
        $atInfo = $info['at_info'];
        $urlInfo = $info['url_info'];
        $topicInfo = $info['topic_info'];
        $obj->content = $info['content'];
        $obj->at_info = $atInfo ? json_encode($atInfo, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : '';
        $obj->url_info = $urlInfo ? json_encode($urlInfo, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : '';
        $obj->topic_info = $topicInfo ? json_encode($topicInfo, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : '';
        $obj->source_activity_id = $sourceActivityId;
        $obj->origin_activity_id = $originActivityId;
        $obj->relay_count = $relayCount;
        $obj->comment_count = $commentCount;
        $obj->like_count = $likeCount;
        $obj->language = $language;
        $obj->status = EchoUserActivity::STATUS_ENABLE;
        $obj->create_time = $createTime ? : time();
        $obj->share = $share;
        $obj->device = 0;
        $obj->save();

        if(!$obj->id) {
            $argStr = json_encode(func_get_args());
            Yii::error("$argStr - [echo user behavior create] model create error");
            return false;
        }

        if($topicInfo) {
            foreach ($topicInfo as $topicId) {
                if(EchoUserActivityTopic::get($topicId)) {
                    EchoTopicUserActivity::create($topicId, $activityId, $type);
                }
            }
        }

        self::getCache()->hMSet($obj->activity_id, $obj->toArray());

        return $obj->id;
    }

    public static function get($activityId)
    {
        if(self::getCache()->has($activityId)) {
            $result = self::getCache()->hGetAll($activityId);
            $status = (int) Util::get($result, 'status');
            if($status != EchoUserActivity::STATUS_ENABLE) {
                return [];
            }
            self::getCache()->autoIncreaseTtl($activityId);

        }
        else {
            $result = self::key($activityId)->findOne(['activity_id' => $activityId]);
            $result = $result ? ($result->status == EchoUserActivity::STATUS_ENABLE ? $result->toArray() : []) : [];
            if($result) {
                self::getCache()->hMSet($activityId, $result);
            }
        }

        if(!$result) {
            return [];
        }

        $result['share'] = isset($result['share']) ? $result['share'] : 0;
        $result['device'] = isset($result['device']) ? $result['device'] : 0;

        $result['at_info'] = isset($result['at_info']) ? EchoUser::getAtInfoUsers($result['at_info']) : [];
        $result['url_info'] = isset($result['url_info']) ? StringUtil::getUrlInfo($result['url_info']): [];
        $result['topic_info'] = isset($result['topic_info']) ? EchoUserActivityTopic::getParseTopicInfo($result['topic_info']) : [];

        $objType = (int) Util::get($result, 'obj_type');
        $objId = (int) Util::get($result, 'obj_id');
        $buildStuff = $objType && $objId && array_key_exists($objType, self::$objTypeMap) ? [self::$objTypeMap[$objType] => $objId] : [];
        if($buildStuff) {
            $result += $buildStuff;
        }

        return $result;
    }

    public static function updateStatus($activityId, $status = EchoUserActivity::STATUS_DISABLE)
    {
        self::key($activityId)->updateAll(['status' => $status], ['activity_id' => $activityId]);
        self::getCache()->hSet($activityId, 'status', $status);
    }

    public static function updateCount($field, $activityId, $count = 1)
    {
        $activity = self::get($activityId);

        if(!$activity) {
            return false;
        }

        $fieldCount = (int) Util::get($activity, $field);
        $fieldCount += $count;

        if($fieldCount >= 0) {
            self::key($activityId)->updateAll([$field => $fieldCount], ['activity_id' => $activityId]);
            self::getCache()->hSet($activityId, $field, $fieldCount);
        }
    }

    public static function getCache()
    {
        return Util::cache('userBehavior');
    }

    protected static function getDevice()
    {
        $source = (int) Util::getSource();
        return $source > 1 ? $source : self::DEVICE_WEB;
    }
}
