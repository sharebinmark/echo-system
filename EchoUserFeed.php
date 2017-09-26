<?php

namespace app\models;

use app\library\constant\EchoExceptionConstant;
use app\library\service\PushService;
use app\models\base\BaseEchoUserFeed;
use app\models\echofamous\EchoFamousUser;
use app\models\echofamous\EchoFamousUserExt;
use app\models\echofamous\EchoMusicVideo;
use app\library\Util;
use app\library\chinesetrans\ZhConversion;
use app\library\constant\EchoGlobalConstant;
use app\library\service\ChannelService;
use app\library\service\UserService;
use app\library\service\beat\BeatService;
use app\models\echolive\EchoLiveChannel;
use myYii\ServiceException;
use yii;

class EchoUserFeed extends BaseEchoUserFeed
{
    const FEED_ORIGIN_NULL = 0;
    const FEED_ORIGIN_SELF = 1;
    const FEED_ORIGIN_FRIEND = 2;
    const FEED_ORIGIN_RECOMMEND = 3;

    const FEED_TYPE_ORIGIN_DELETED = -1;
    const FEED_TYPE_DEFAULT = 0;
    const FEED_TYPE_LIKE = 1;
    const FEED_TYPE_LOOP = 2;
    const FEED_TYPE_RELAY = 3;
    const FEED_TYPE_BIRTHDAY = 4;
    const FEED_TYPE_PROMOTION_FOLLOW_CHANNEL = 5;
    const FEED_TYPE_PROMOTION_FL_SOUND = 6;
    const FEED_TYPE_FOLLOW_CHANNEL = 7;
    const FEED_TYPE_COMPOSE_EVENT_CONTENT = 8;
    const FEED_TYPE_LIKE_EVENT_CONTENT = 9;
    const FEED_TYPE_PROMOTION_ACTIVITY = 10;
    const FEED_TYPE_EXPRESSION = 11;
    const FEED_TYPE_FOLLOW_USER = 12;
    const FEED_TYPE_SHARE_EVENT = 13;
    const FEED_TYPE_UPLOAD_SOUND = 14;
    const FEED_TYPE_SHARE_MV = 15;
    const FEED_TYPE_UPLOAD_MV = 16;
    const FEED_TYPE_SHARE_TV_CHANNEL = 17;
    const FEED_TYPE_SHARE_TOPIC = 18;
    const FEED_TYPE_SHARE_GAME_SCORE = 19;
    const FEED_TYPE_SHARE_GAME_SOUND = 20;
    const FEED_TYPE_SHARE_DAILY_SIGN = 21;
    const FEED_TYPE_SHARE_ECHO_GROUP = 22;
    const FEED_TYPE_SHARE_CHANNEL = 23;

    public static $feedTypeMap = [
        self::FEED_TYPE_LIKE => EchoUserBehavior::OBJ_TYPE_SOUND,
        self::FEED_TYPE_FOLLOW_CHANNEL => EchoUserBehavior::OBJ_TYPE_CHANNEL,
        self::FEED_TYPE_COMPOSE_EVENT_CONTENT => EchoUserBehavior::OBJ_TYPE_EVENT_CONTENT,
        self::FEED_TYPE_LIKE_EVENT_CONTENT => EchoUserBehavior::OBJ_TYPE_EVENT_CONTENT,
        self::FEED_TYPE_EXPRESSION => EchoUserBehavior::OBJ_TYPE_EXPRESS,
        self::FEED_TYPE_FOLLOW_USER => EchoUserBehavior::OBJ_TYPE_USER,
        self::FEED_TYPE_SHARE_EVENT => EchoUserBehavior::OBJ_TYPE_EVENT,
        self::FEED_TYPE_UPLOAD_SOUND => EchoUserBehavior::OBJ_TYPE_SOUND,
        self::FEED_TYPE_SHARE_MV => EchoUserBehavior::OBJ_TYPE_MV,
        self::FEED_TYPE_UPLOAD_MV => EchoUserBehavior::OBJ_TYPE_MV,
        self::FEED_TYPE_SHARE_TV_CHANNEL => EchoUserBehavior::OBJ_TYPE_TV_CHANNEL,
        self::FEED_TYPE_SHARE_TOPIC => EchoUserBehavior::OBJ_TYPE_TOPIC,
        self::FEED_TYPE_SHARE_GAME_SCORE => EchoUserBehavior::OBJ_TYPE_GAME_SHARE,
        self::FEED_TYPE_SHARE_GAME_SOUND => EchoUserBehavior::OBJ_TYPE_GAME_SOUND,
        self::FEED_TYPE_SHARE_DAILY_SIGN => EchoUserBehavior::OBJ_TYPE_SOUND,
        self::FEED_TYPE_SHARE_ECHO_GROUP => EchoUserBehavior::OBJ_TYPE_ECHO_GROUP,
        self::FEED_TYPE_SHARE_CHANNEL => EchoUserBehavior::OBJ_TYPE_CHANNEL,
    ];

    const FEED_CONTENT_LIMIT = 2000;
    const FEED_COMMENT_CONTENT_LIMIT = 1000;
    const FEED_SOUND_CONTENT_LIMIT = 100;
    const FEED_LIMIT_FOR_FOLLOW = 30;
    const FEED_CACHE_COUNT = 200;
    const FEED_CACHE_OUT_COUNT = 10;
    const FEED_ACHIEVE_LIMIT = 10;
    const FEED_MERGE_ACHIEVE_LIMIT = 100;
    const FEED_PULL_QUEUE_COUNT = 10;

    const FEED_LABEL_TEXT_DEFAULT = '';
    const FEED_LABEL_TEXT_LIKE_SOUND = 'feed like sound';
    const FEED_LABEL_TEXT_LIKE_MV = 'feed like MV';
    const FEED_LABEL_TEXT_UPLOAD_SOUND = 'feed upload sound';
    const FEED_LABEL_TEXT_SHARE_SOUND = 'feed share sound';
    const FEED_LABEL_TEXT_SHARE_MV = 'feed share MV';
    const FEED_LABEL_TEXT_UPLOAD_MV = 'feed upload MV';
    const FEED_LABEL_TEXT_FOLLOW_CHANNEL = 'feed follow channel';
    const FEED_LABEL_TEXT_FOLLOW_USER = 'feed follow user';
    const FEED_LABEL_TEXT_SHARE_EVENT = 'feed share event';
    const FEED_LABEL_TEXT_JOIN_EVENT = 'feed join event';
    const FEED_LABEL_TEXT_LIKE_EVENT_CONTENT = 'feed like event content';
    const FEED_LABEL_TEXT_SHARE_EVENT_CONTENT = 'feed share event content';
    const FEED_LABEL_TEXT_RELAY = 'feed text relay';
    const FEED_LABEL_TEXT_SHARE_TV_CHANNEL = 'feed share tv channel';
    const FEED_LABEL_TEXT_SHARE_TOPIC = 'feed share topic';
    const FEED_LABEL_TEXT_SHARE_GAME_SCORE = 'feed share game score';
    const FEED_LABEL_TEXT_SHARE_GAME_SOUND = 'feed share game sound';
    const FEED_LABEL_TEXT_UPLOAD_COVER_SOUND = 'feed upload cover sound';
    const FEED_LABEL_TEXT_UPLOAD_STAR_SINGER_SOUND = 'feed upload star singer sound';
    const FEED_LABEL_TEXT_UPLOAD_SHORT_VIDEO_SOUND = 'feed upload short video sound';
    const FEED_LABEL_TEXT_SHARE_DAILY_SIGN = 'feed share daily sign';
    const FEED_LABEL_TEXT_SHARE_ECHO_GROUP = 'feed share echo group';
    const FEED_LABEL_TEXT_SHARE_CHANNEL = 'feed share channel';

    const FEED_LABEL_ICON_DEFAULT = 0;
    const FEED_LABEL_ICON_LIKE_MUSIC = 1;
    const FEED_LABEL_ICON_UPLOAD_MUSIC = 2;
    const FEED_LABEL_ICON_FOLLOW_USER = 3;
    const FEED_LABEL_ICON_FOLLOW_CHANNEL = 4;
    const FEED_LABEL_ICON_EVENT = 5;

    const FEED_LABEL_CONTENT_DEFAULT = '';
    const FEED_LABEL_CONTENT_SOUND = 'feed label sound';
    const FEED_LABEL_CONTENT_MV = 'feed label mv';
    const FEED_LABEL_CONTENT_CHANNEL = 'feed label channel';
    const FEED_LABEL_CONTENT_EVENT = 'feed label event';
    const FEED_LABEL_CONTENT_EVENT_CONTENT = 'feed label event content';
    const FEED_LABEL_CONTENT_USER = 'feed label user';

    const FEED_CONTENT_BIRTHDAY_SELF = 'feed today is your birthday, echo for you to send a birthday song, to listen to see it';
    const FEED_CONTENT_BIRTHDAY_OTHER = 'feed This is echo send Ta birthday song, remember that happy birthday oh';
    const FEED_CONTENT_LIKE = 'feed like this sound';
    const FEED_CONTENT_FOLLOW_CHANNEL = 'feed follow the channel';
    const FEED_CONTENT_COMPOSE_EVENT_CONTENT = '';
    const FEED_CONTENT_LIKE_EVENT_CONTENT = 'feed like the event content';
    const FEED_CONTENT_FOLLOW_USER = 'feed follow TA';
    const FEED_CONTENT_SHARE_EVENT = 'feed share the activity';

    const FEED_TIP_SOUND = 'feed the original echo has been deleted';
    const FEED_TIP_EXPRESSION = 'feed associated with the sound has been deleted';
    const FEED_TIP_MV = 'feed related MV has been deleted';
    const FEED_TIP_CHANNEL = 'feed related channel has been deleted';
    const FEED_TIP_EVENT = 'feed related activities have been deleted';
    const FEED_TIP_SOUND_CHECK = 'feed this sound audit';
    const FEED_TIP_SOUND_ILLEGAL = 'This music work is not displayed according to local copyright restriction';
    const FEED_TIP_FOLLOW_USER = 'feed attention to the user has been deleted';
    const FEED_TIP_TV_CHANNEL = 'feed related tv channel has been deleted';
    const FEED_TIP_TOPIC = 'feed related topic has been deleted';
    const FEED_TIP_ECHO_GROUP = 'feed related echo group has been deleted';

    const FEED_TIP_PIC_SOUND = 'http://7xl0br.media1.z0.glb.clouddn.com/20160327%2Fvocal_delete%402x.png';

    const FEED_INSERT_DEFAULT_QUEUE = 'feed_insert_router';

    const FEED_TIPS_FIELD_AVATAR = 'avatar';
    const FEED_TIPS_FIELD_NEW_NUM = 'new_num';
    const FEED_TIPS_FIELD_FRIEND_AVATAR = 'friend_avatar';
    const FEED_TIPS_FIELD_HAS_FRIEND = 'has_friend';
    const FEED_TIPS_FIELD_FRESH_TIME = 'fresh_time';

    const FEED_KEEP_USERS_KEY = 'feed_keep_users';

    const FEED_SHARE_URL = 'https://www.app-echo.com/#/feed/';

    public static $ARConfig = ['table_name' => 'echo_user_feed_new'];
    
    public static function labelArr(){
        return [
            self::FEED_TYPE_LIKE => ['text' =>  \Yii::t('app', self::FEED_LABEL_TEXT_LIKE_SOUND), 'icon' => self::FEED_LABEL_ICON_LIKE_MUSIC, 'content' => self::FEED_LABEL_CONTENT_DEFAULT],
            self::FEED_TYPE_FOLLOW_CHANNEL => ['text' => \Yii::t('app', self::FEED_LABEL_TEXT_FOLLOW_CHANNEL), 'icon' => self::FEED_LABEL_ICON_FOLLOW_CHANNEL, 'content' => \Yii::t('app', self::FEED_LABEL_CONTENT_CHANNEL)],
            self::FEED_TYPE_COMPOSE_EVENT_CONTENT => ['text' => \Yii::t('app', self::FEED_LABEL_TEXT_JOIN_EVENT), 'icon' => self::FEED_LABEL_ICON_EVENT, 'content' => \Yii::t('app', self::FEED_LABEL_CONTENT_EVENT_CONTENT)],
            self::FEED_TYPE_LIKE_EVENT_CONTENT => ['text' => \Yii::t('app', self::FEED_LABEL_TEXT_LIKE_EVENT_CONTENT), 'icon' => self::FEED_LABEL_ICON_EVENT, 'content' => \Yii::t('app', self::FEED_LABEL_CONTENT_EVENT_CONTENT)],
            self::FEED_TYPE_SHARE_EVENT => ['text' => \Yii::t('app', self::FEED_LABEL_TEXT_SHARE_EVENT), 'icon' => self::FEED_LABEL_ICON_EVENT, 'content' => \Yii::t('app', self::FEED_LABEL_CONTENT_EVENT)],
            self::FEED_TYPE_FOLLOW_USER => ['text' => \Yii::t('app', self::FEED_LABEL_TEXT_FOLLOW_USER), 'icon' => self::FEED_LABEL_ICON_FOLLOW_USER, 'content' => self::FEED_LABEL_CONTENT_DEFAULT],
            self::FEED_TYPE_RELAY => ['text' => \Yii::t('app', self::FEED_LABEL_TEXT_RELAY), 'icon' => self::FEED_LABEL_ICON_DEFAULT, 'content' => self::FEED_LABEL_CONTENT_DEFAULT],
            self::FEED_TYPE_BIRTHDAY => ['text' => self::FEED_LABEL_TEXT_DEFAULT, 'icon' => self::FEED_LABEL_ICON_DEFAULT, 'content' => self::FEED_LABEL_CONTENT_DEFAULT],
            self::FEED_TYPE_UPLOAD_SOUND => ['text' => \Yii::t('app', self::FEED_LABEL_TEXT_UPLOAD_SOUND), 'icon' => self::FEED_LABEL_ICON_UPLOAD_MUSIC, 'content' => self::FEED_LABEL_CONTENT_DEFAULT],
            self::FEED_TYPE_UPLOAD_MV => ['text' => \Yii::t('app', self::FEED_LABEL_TEXT_UPLOAD_MV), 'icon' => self::FEED_LABEL_ICON_DEFAULT, 'content' => self::FEED_LABEL_CONTENT_DEFAULT],
            self::FEED_TYPE_SHARE_MV => ['text' => \Yii::t('app', self::FEED_LABEL_TEXT_SHARE_MV), 'icon' => self::FEED_LABEL_ICON_DEFAULT, 'content' => self::FEED_LABEL_CONTENT_DEFAULT],
            self::FEED_TYPE_SHARE_TV_CHANNEL => ['text' => \Yii::t('app', self::FEED_LABEL_TEXT_SHARE_TV_CHANNEL), 'icon' => self::FEED_LABEL_ICON_DEFAULT, 'content' => self::FEED_LABEL_CONTENT_DEFAULT],
            self::FEED_TYPE_SHARE_TOPIC => ['text' => \Yii::t('app', self::FEED_LABEL_TEXT_SHARE_TOPIC), 'icon' => self::FEED_LABEL_ICON_DEFAULT, 'content' => self::FEED_LABEL_CONTENT_DEFAULT],
            self::FEED_TYPE_SHARE_GAME_SCORE => ['text' => \Yii::t('app', self::FEED_LABEL_TEXT_SHARE_GAME_SCORE), 'icon' => self::FEED_LABEL_ICON_DEFAULT, 'content' => self::FEED_LABEL_CONTENT_DEFAULT],
            self::FEED_TYPE_SHARE_GAME_SOUND => ['text' => \Yii::t('app', self::FEED_LABEL_TEXT_SHARE_GAME_SOUND), 'icon' => self::FEED_LABEL_ICON_DEFAULT, 'content' => self::FEED_LABEL_CONTENT_DEFAULT],
            self::FEED_TYPE_SHARE_DAILY_SIGN => ['text' => \Yii::t('app', self::FEED_LABEL_TEXT_SHARE_DAILY_SIGN), 'icon' => self::FEED_LABEL_ICON_DEFAULT, 'content' => self::FEED_LABEL_CONTENT_DEFAULT],
            self::FEED_TYPE_SHARE_ECHO_GROUP => ['text' => \Yii::t('app', self::FEED_LABEL_TEXT_SHARE_ECHO_GROUP), 'icon' => self::FEED_LABEL_ICON_DEFAULT, 'content' => self::FEED_LABEL_CONTENT_DEFAULT],
            self::FEED_TYPE_SHARE_CHANNEL => ['text' => \Yii::t('app', self::FEED_LABEL_TEXT_SHARE_CHANNEL), 'icon' => self::FEED_LABEL_ICON_DEFAULT, 'content' => self::FEED_LABEL_CONTENT_DEFAULT],
        ];
    }

    public static $uaSettingTypeMap = [
        self::FEED_TYPE_UPLOAD_SOUND => EchoUserExt::UA_SETTING_TYPE_UPLOAD_SOUND,
        self::FEED_TYPE_LIKE => EchoUserExt::UA_SETTING_TYPE_LIKE_SOUND,
        self::FEED_TYPE_FOLLOW_USER => EchoUserExt::UA_SETTING_TYPE_FOLLOW_USER,
        self::FEED_TYPE_FOLLOW_CHANNEL => EchoUserExt::UA_SETTING_TYPE_FOLLOW_CHANNEL,
        self::FEED_TYPE_COMPOSE_EVENT_CONTENT => EchoUserExt::UA_SETTING_TYPE_JOIN_EVENT,
        self::FEED_TYPE_LIKE_EVENT_CONTENT => EchoUserExt::UA_SETTING_TYPE_LIKE_EVENT_CONTENT,
    ];
    
    public static $allOriginArr = [self::FEED_ORIGIN_NULL, self::FEED_ORIGIN_SELF, self::FEED_ORIGIN_FRIEND];

    public static $isSendPushTypes = [self::FEED_TYPE_DEFAULT, self::FEED_TYPE_UPLOAD_SOUND, self::FEED_TYPE_SHARE_MV];

    public static function createFeed($userId, $activityId, $followingId, $type = self::FEED_TYPE_DEFAULT, $createTime = 0)
    {
        if(self::isAchieve($userId, $activityId)) {
            return false;
        }

        $id = self::getAutoId();
        $origin = self::getOrigin($userId, $followingId);
        $createTime = $createTime ? : time();
        $freshTime = self::getFreshTime($userId);

        $db = self::key($userId)->getDb();
        $tableName = self::key($userId)->tableName();

        $sql = "INSERT IGNORE INTO {$tableName} (id, user_id, activity_id, following_id, origin, type, create_time) ";
        $sql .= "VALUES ({$id}, {$userId}, {$activityId}, {$followingId}, {$origin}, {$type}, {$createTime})";
        $ret = Util::executeSqlFromMaster($sql, [], $db);

        if(!$ret) {
            return false;
        }


        $key = self::getFeedsListCacheKey($userId, $origin);
        $allKey = self::getFeedsListCacheKey($userId, self::FEED_ORIGIN_NULL);
        self::feedsListZAdd($key, $createTime, $activityId);
        if($origin != self::FEED_ORIGIN_NULL) {
            self::feedsListZAdd($allKey, $createTime, $activityId);
        }

        if($createTime > $freshTime) {
            if($origin != self::FEED_ORIGIN_SELF) {
                self::getFeedTipsCache()->hIncrBy($userId, self::FEED_TIPS_FIELD_NEW_NUM);
                self::getFeedTipsCache()->autoIncreaseTtl($userId);
            }
            if($origin == self::FEED_ORIGIN_FRIEND) {
                if(!self::hasFriendFeed($userId)) {
                    self::hSetFeedTips($userId, self::FEED_TIPS_FIELD_HAS_FRIEND, 1);
                }
                self::hSetFeedTips($userId, self::FEED_TIPS_FIELD_FRIEND_AVATAR, $followingId);
            }
        }

        return $id;
    }

    public static function deleteFeedByActivityId($activityId, $userId)
    {
        self::feedsListRemove($userId, $activityId);
        self::key($userId)->deleteAll(['user_id' => $userId, 'activity_id' => $activityId]);
        EchoRecommendUserActivity::remove($activityId);
    }

    public static function getFeedsByFollowingId($userId, $followingId, $page = 1, $limit = 1000)
    {
        $offset = ($page - 1) * $limit;
        $feeds = self::key($userId)->find()
            ->select('id, activity_id')
            ->where(['user_id' => $userId, 'following_id' => $followingId])
            ->offset($offset)
            ->limit($limit)
            ->all();

        if(!$feeds) {
            return [];
        }

        return $feeds;
    }

    public static function deleteFeedByUserId($userId, $followingId)
    {
        Util::enqueue(['user_id' => $userId, 'following_id' => $followingId], '', 'remove_follow_router');
    }

    public static function getFeedsCount($userId, $origin = 0)
    {
        $key = self::getFeedsListCacheKey($userId, $origin);
        if(self::getFeedsListCache()->has($key)) {
            $ret = self::getFeedsListCache()->zCard($key);
        }
        else {
            $where = $origin ? ['user_id' => $userId, 'origin' => $origin] : ['user_id' => $userId];
            $ret = self::key($userId)->find()->where($where)->count();
        }

        return (int) $ret;
    }

    public static function getFreshTime($userId)
    {
        if(self::getFeedTipsCache()->hHas($userId, self::FEED_TIPS_FIELD_FRESH_TIME)) {
            $result = (int) self::getFeedTipsCache()->hGet($userId, self::FEED_TIPS_FIELD_FRESH_TIME);
        }
        else {
            $feeds = self::getFeedsLine($userId, 1, self::FEED_ORIGIN_NULL, 1);
            $result =  isset($feeds[0]) ? (int) Util::get($feeds[0], EchoUserActivity::FIELD_CREATE_TIME) : 0;
        }

        return $result;
    }

    public static function getLastFollowing($userId, $enqueue = true)
    {
        if(YII_ENV == 'stress_test' || Util::isStressTest()) {
            return;
        }

        $freshTime = self::getFreshTime($userId);
        Util::systemCall(['\\' . get_called_class() . '::getActivityUsersList', $freshTime, $userId, $enqueue], 'activity_users_list_router', $enqueue);
    }

    public static function getPullQueueName($userId)
    {
        $key = $userId % self::FEED_PULL_QUEUE_COUNT;

        return 'feed_data_by_ct_'.$key.'_router';
    }

    public static function getActivityUsersList($freshTime, $userId, $enqueue = true)
    {
        $followingIds = SpaFollow::getFollowingIdByUserId($userId);
        if($followingIds) {
            $queueName = self::getPullQueueName($userId);
            foreach($followingIds as $followingId) {
                $lastTime = EchoUserActivityLine::getLastTime($followingId);
                if($lastTime > $freshTime) {
                    Util::systemCall(['\\' . get_called_class() . '::getFeedsDataByCt', $userId, $followingId, $freshTime], $queueName, $enqueue);
                }
            }
        }
    }

    public static function getFeedsDataByCt($currentUserId, $userId, $minCreateTime, $maxCreateTime = 0, $origin = 1)
    {
        $result = [];
        $key = self::getFeedsListCacheKey($userId, $origin);
        if(self::getFeedsListCache()->has($key)) {
            $maxCreateTime = $maxCreateTime ? : '+inf';
            $feedsArr = self::getFeedsListCache()->zRevRangeByScore($key, $maxCreateTime, $minCreateTime, ['withscores' => true,'limit' => [0, self::FEED_CACHE_COUNT]]);
            if($feedsArr) {
                foreach($feedsArr as $k => $v) {
                    self::insertByActivityId($currentUserId, $k);
                    $result[] = (int) $v;
                }
            }
        }
        else {
            $result = self::getFeedsDataByCtDb($currentUserId, $userId, $minCreateTime, $maxCreateTime, $origin);
        }

        return $result;
    }

    public static function getFeedsDataByCtDb($currentUserId, $userId, $minCreateTime, $maxCreateTime = 0, $origin = 1)
    {
        $where = $origin ? ['user_id' => $userId, 'origin' => $origin] : ['user_id' => $userId];
        $andWhere = $maxCreateTime ?
            ['and', ['>', 'create_time', $minCreateTime], ['<', 'create_time', $maxCreateTime]] :
            ['>', 'create_time', $minCreateTime];
        $feedsArr = self::key($userId)->find()
            ->select('activity_id, create_time')
            ->forceIndex('idx_uid_origin_ct_aid')
            ->where($where)
            ->andWhere($andWhere)
            ->orderBy('create_time desc')
            ->limit(self::FEED_CACHE_COUNT)
            ->all();

        if(!$feedsArr) {
            return [];
        }

        $result = [];
        foreach($feedsArr as $feed) {
            self::insertByActivityId($currentUserId, $feed->activity_id);
            $result[] = $feed->create_time;
        }

        return $result;
    }

    public static function getFeedsDb($userId, $page = 1, $origin = 0, $limit = 10)
    {
        $offset = ($page - 1) * $limit;

        $db = self::key($userId)->getDb();
        $tableName = self::key($userId)->tableName();

        $sqlAll  = "SELECT activity_id, create_time FROM {$tableName} FORCE INDEX(idx_uid_ct) WHERE user_id = {$userId} ORDER BY create_time DESC LIMIT {$offset}, {$limit}";
        $sqlOrigin  = "SELECT activity_id, create_time FROM {$tableName} FORCE INDEX(idx_uid_origin_ct_aid) WHERE user_id = {$userId} AND origin = {$origin} ORDER BY create_time DESC LIMIT {$offset}, {$limit}";
        $sql = $origin ? $sqlOrigin : $sqlAll;
        $feeds = $db->createCommand($sql)->queryAll();

        if(!$feeds) {
            return [];
        }

        return $feeds;
    }
    
    public static function getFeedsCache($userId, $page = 1, $origin = 0, $limit = 10)
    {
        $offset = ($page - 1) * $limit;
        $key = self::getFeedsListCacheKey($userId, $origin);
        $feedsArr = self::getFeedsListCache()->zRevRange($key, $offset, $offset + $limit - 1, true);
        
        if(!$feedsArr) {
            return [];
        }

        $feeds = [];
        foreach($feedsArr as $k => $v) {
            $feeds[] = [
                EchoUserActivity::FIELD_ACTIVITY_ID => (int) $k,
                EchoUserActivity::FIELD_CREATE_TIME => (int) $v,
            ];
        }

        self::getFeedsListCache()->autoIncreaseTtl($key);
        
        return $feeds;
    }

    public static function generateFeedsListCache($userId, $origin)
    {
        $feeds = self::getFeedsDb($userId, 1, $origin, self::FEED_CACHE_COUNT);
        if($feeds) {
            $key = self::getFeedsListCacheKey($userId, $origin);
            foreach($feeds as $feed) {
                self::getFeedsListCache()->zAdd($key, $feed[EchoUserActivity::FIELD_CREATE_TIME], $feed[EchoUserActivity::FIELD_ACTIVITY_ID]);
            }
        }
    }

    public static function getFeedsLine($userId, $page = 1, $origin = 0, $limit = 10)
    {
        $key = self::getFeedsListCacheKey($userId, $origin);
        if(self::getFeedsListCache()->has($key)) {
            $feeds = self::getFeedsCache($userId, $page, $origin, $limit);
            if(!$feeds) {
                $feeds = self::getFeedsDb($userId, $page, $origin, $limit);
            }
        }
        else {
            $feeds = self::getFeedsDb($userId, $page, $origin, $limit);
            Util::systemCall(['\\' . get_called_class() . '::generateFeedsListCache', $userId, $origin], 'generate_feeds_list_cache_router');
        }

        if(!$feeds) {
            return [];
        }

        return $feeds;
    }

    public static function getFeedsData($userId, $page = 1, $origin = 0, $limit = 10)
    {
        $feeds = self::getFeedsLine($userId, $page, $origin, $limit);

        if(!$feeds) {
            return [];
        }

        if($page == 1) {
            $origin == self::FEED_ORIGIN_FRIEND ? self::deleteNewFriendFeedKey($userId) : null;
            $origin == self::FEED_ORIGIN_NULL ? self::deleteNewFeedNumKey($userId) : null;
            $freshTime = (int) Util::get($feeds[0], EchoUserActivity::FIELD_CREATE_TIME);
            self::hSetFeedTips($userId, self::FEED_TIPS_FIELD_FRESH_TIME, $freshTime);
        }

        foreach($feeds as $k => &$feed) {
            $activity = EchoUserActivity::getActivity($feed['activity_id']);
            self::getFeedStuff($feed, $activity);
            self::filterFeed($feeds, $feed, $k, $activity);
        }

        return array_values($feeds);
    }

    public static function getFeeds($userId, $page = 1, $origin = self::FEED_ORIGIN_NULL)
    {
        if($origin == self::FEED_ORIGIN_RECOMMEND) {
            return EchoRecommendUserActivity::getList($page, false);
        }

        if($page == 1 && !$origin) {
            self::getLastFollowing($userId);
        }

        $feeds = self::getFeedsData($userId, $page, $origin);

        if(!$feeds) {
            return [];
        }

        if(Util::isNewVersion(20150920, 79) && !$origin) {
            $followChannelPromotion = self::getFollowChannelPromotion($userId, $feeds, $page);
            if($followChannelPromotion) {
                $feeds = self::addPromotion($followChannelPromotion, $feeds, count($feeds));
            }

            $flSoundPromotion = self::getFLSoundPromotion($userId, $feeds, $page);
            if($flSoundPromotion) {
                $feeds = self::addPromotion($flSoundPromotion, $feeds, count($feeds));
            }
        }

        return $feeds;
    }

    public static function getFeedStuff(&$feed, $activity = [])
    {
        $activity = $activity ? : EchoUserActivity::getActivity($feed['activity_id']);
        $originActivityId = (int) Util::get($activity, EchoUserActivity::FIELD_ORIGIN_ACTIVITY_ID);
        $publisherId = (int) Util::get($activity, EchoUserActivity::FIELD_USER_ID);
        $soundId = (int) Util::get($activity, EchoUserActivity::FIELD_SOUND_ID);
        $expressId = (int) Util::get($activity, EchoUserActivity::FIELD_EXPRESS_ID);
        $mvId = (int) Util::get($activity, EchoUserActivity::FIELD_MV_ID);
        $channelId = (int) Util::get($activity, EchoUserActivity::FIELD_CHANNEL_ID);
        $eventContentId = (int) Util::get($activity, EchoUserActivity::FIELD_EVENT_CONTENT_ID);
        $eventId = (int) Util::get($activity, EchoUserActivity::FIELD_EVENT_ID);
        $content = Util::get($activity, EchoUserActivity::FIELD_CONTENT);
        $followUserId = (int) Util::get($activity, EchoUserActivity::FIELD_FOLLOW_USER_ID);
        $tvChannelId = (int) Util::get($activity, EchoUserActivity::FIELD_TV_CHANNEL_ID);
        $topicId = (int) Util::get($activity, EchoUserActivity::FIELD_TOPIC_ID);
        $gameShareId = (int) Util::get($activity, EchoUserActivity::FIELD_GAME_SHARE_ID);
        $gameSoundId = (int) Util::get($activity, EchoUserActivity::FIELD_GAME_SOUND_ID);
        $echoGroupId = (int) Util::get($activity, EchoUserActivity::FIELD_ECHO_GROUP_ID);
        $imageCount = (int) Util::get($activity, EchoUserActivity::FIELD_IMAGE_COUNT);
        $buildType = (int) Util::get($activity, EchoUserActivity::FIELD_TYPE);
        $imageActivityId = $feed['activity_id'];
        $sound = [];
        $soundInfo = null;
        $currentUserId = (int) Yii::$app->getUser()->getId();
        $share = (int) Util::get($activity, 'share');
        $device = (int) Util::get($activity, 'device');

        $feed['type'] = (int) Util::get($activity, EchoUserActivity::FIELD_TYPE);
        $feed['origin'] = self::getOrigin($currentUserId, $publisherId);
        $feed['publisher'] = self::formatUser($publisherId);

        $shareImage = Util::get($feed['publisher'], 'avatar');
        $shareText = '分享内容';

        if($originActivityId) {
            $originActivity = EchoUserActivity::getActivity($originActivityId);
            $originPublisherId = (int) Util::get($originActivity, EchoUserActivity::FIELD_USER_ID);
            $feed['origin_publisher'] = $originActivity ? self::formatUser($originPublisherId) : [];
            if($feed['origin_publisher']) {
                $feed['origin_type'] = (int) Util::get($originActivity, EchoUserActivity::FIELD_TYPE);
                $buildType = $feed['origin_type'];
                $imageCount = (int) Util::get($originActivity, EchoUserActivity::FIELD_IMAGE_COUNT);
                $imageActivityId = $originActivityId;
                $originContent = Util::get($originActivity, EchoUserActivity::FIELD_CONTENT);

                $feed['origin_label_text'] = self::formatLabel($feed['origin_type'], $soundId, 'text', $sound);
                $feed['origin_label_icon'] = self::formatLabel($feed['origin_type'], $soundId, 'icon', $sound);
                $feed['origin_label_content'] = self::formatLabel($feed['origin_type'], $soundId, 'content', $sound);
                $feed['origin_content'] = self::formatContent($originContent, $soundInfo, $originActivity['type'], $feed['origin']);
                $feed['origin_url_info'] = Util::get($originActivity, EchoUserActivity::FIELD_URL_INFO);
                $feed['origin_at_info'] = Util::get($originActivity, EchoUserActivity::FIELD_AT_INFO);

                $fakeRelayCount = $activity ? EchoUserActivity::getFakeFieldCount($activity, EchoUserActivity::FIELD_RELAY_COUNT) : 0;
                $feed['origin_like_num'] = EchoUserActivity::getFieldCount($originActivity);
                $feed['origin_comment_num'] = (int) Util::get($originActivity, EchoUserActivity::FIELD_COMMENT_COUNT, 0);
                $feed['origin_relay_num'] = EchoUserActivity::getFieldCount($originActivity, EchoUserActivity::FIELD_RELAY_COUNT) + $fakeRelayCount;
                $feed['origin_create_time'] = (int) Util::get($originActivity, EchoUserActivity::FIELD_CREATE_TIME, 0);
            }
            else {
                $feed['type'] = self::FEED_TYPE_ORIGIN_DELETED;
            }
        }

        if($imageCount) {
            $feed['image'] = EchoUserActivityImage::getActivityImage($imageActivityId);
            if($feed['type'] == self::FEED_TYPE_SHARE_DAILY_SIGN) {
                $feed['image'] = Util::get($feed['image'], 0) ? [Util::get($feed['image'], 0)] : [];
            }
            $shareImageInfo = Util::get($feed['image'], 0);
            $shareImage = Util::get($shareImageInfo, 'origin');
        }

        if($soundId) {
            $sound = self::formatSound($soundId);
            $feed['sound'] = $sound;
            if($feed['sound']) {
                \Yii::$app->qiniu->attachSoundStates($feed['sound'], ['copyright_v2_source' => 'source', /*'lsb_copyright_source' => 'source',*/]);
            }
            $soundInfo = Util::get($feed['sound'], 'info');
            $soundStatus = (int) Util::get($feed['sound'], 'status');
            if((Util::supportShortVideoFeed() || !EchoSound::isShortVideo($sound)) && $soundStatus != EchoSound::SOUND_STATUS_NORMAL) {
                if($soundStatus == EchoSound::SOUND_STATUS_PENDING) {
                    if(Util::isNewVersion(20160327, 95) || YII_FRONTEND == 'web') {
                        unset($feed['sound']);
                        $feed['sound_deleted'] = ['text' => \Yii::t('app', self::FEED_TIP_SOUND_CHECK), 'pic' => self::FEED_TIP_PIC_SOUND];
                    }
                    else {
                        $feed['sound'] = EchoSound::fakeSoundForFeed(\Yii::t('app', self::FEED_TIP_SOUND_CHECK));
                    }
                }
                elseif($soundStatus == EchoSound::SOUND_STATUS_ILLEGAL) {
                    if(Util::isNewVersion(20160327, 95) || YII_FRONTEND == 'web') {
                        unset($feed['sound']);
                        $feed['sound_deleted'] = ['text' => \Yii::t('app', self::FEED_TIP_SOUND_ILLEGAL), 'pic' => self::FEED_TIP_PIC_SOUND];
                    }
                    else {
                        $feed['sound'] = EchoSound::fakeSoundForFeed(\Yii::t('app', self::FEED_TIP_SOUND_ILLEGAL));
                    }
                }
                else {
                    if(Util::isNewVersion(20160327, 95) || YII_FRONTEND == 'web') {
                        unset($feed['sound']);
                        $feed['sound_deleted'] = ['text' => \Yii::t('app', self::FEED_TIP_SOUND), 'pic' => self::FEED_TIP_PIC_SOUND];
                    }
                    else {
                        $feed['sound'] = EchoSound::fakeSoundForFeed(\Yii::t('app', self::FEED_TIP_SOUND));
                    }
                }
            }
            $shareImage = isset($feed['sound']) ? Util::get($feed['sound'], 'pic') : $shareImage;
            $shareText = $feed['type'] == self::FEED_TYPE_LIKE ? (isset($feed['sound']) ? '喜欢了'.Util::get($feed['sound'], 'name') : $shareText) : $shareText;
        }

        $feed['origin_activity_id'] = $originActivityId;
        $feed['label_text'] = self::formatLabel($feed['type'], $soundId, 'text', $sound);
        $feed['label_icon'] = self::formatLabel($feed['type'], $soundId, 'icon', $sound);
        $feed['label_content'] = self::formatLabel($feed['type'], $soundId, 'content', $sound);
        $feed['content'] = self::formatContent($content, $soundInfo, $feed['type'], $feed['origin']);
        $feed['url_info'] = Util::get($activity, EchoUserActivity::FIELD_URL_INFO);
        $feed['at_info'] = Util::get($activity, EchoUserActivity::FIELD_AT_INFO);
        $feed['subject_info'] = Util::get($activity, EchoUserActivity::FIELD_TOPIC_INFO);
        $feed['tail'] = self::formatTail($share, $device);

        if($expressId) {
            $feed['expression'] = EchoSoundExpression::formatSound($soundId);
            if(!$feed['expression']) {
                unset($feed['expression']);
                $feed['content'] = \Yii::t('app', self::FEED_TIP_EXPRESSION);
            }
            $shareImage = isset($feed['expression']) ? Util::get($feed['expression'], 'pic') : $shareImage;
        }

        if($mvId) {
            $feed['mv'] = EchoMusicVideo::getForFeed($mvId);
            if(!$feed['mv']) {
                unset($feed['mv']);
                $feed['content'] = \Yii::t('app', self::FEED_TIP_MV);
            }
            $shareImage = isset($feed['mv']) ? Util::get($feed['mv'], 'cover_url') : $shareImage;
            $shareText = '分享视频';
        }

        if($channelId) {
            $feed['channel'] = EchoChannel::getForFeed($channelId);
            if(!$feed['channel']) {
                unset($feed['channel']);
                $feed['content'] = \Yii::t('app', self::FEED_TIP_CHANNEL);
            }
            $shareImage = isset($feed['channel']) ? Util::get($feed['channel'], 'pic') : $shareImage;
            $shareText = isset($feed['channel']) ? '关注了'.Util::get($feed['channel'], 'name') : $shareText;
        }

        if($eventId) {
            $feed['event'] = EchoEvent::getForFeed($eventId);
            if(!$feed['event']) {
                unset($feed['event']);
                $feed['content'] = self::FEED_TIP_EVENT;
            }
        }

        if($eventContentId) {
            $eventContent = EchoEventContent::getForFeed($eventContentId);
            $eventId = (int) Util::get($eventContent, 'event_id');
            $event = EchoEvent::getForFeed($eventId);

            if($event) {
                $feed['event'] =  $event;
                if($eventContentId) {
                    $eventContent = EchoEventContent::getForFeed($eventContentId);
                    if($eventContent) {
                        if($buildType == self::FEED_TYPE_LIKE_EVENT_CONTENT) {
                            $eventContentUserId = (int) Util::get($eventContent, 'user_id');
                            if($eventContentUserId) {
                                $eventContent['user'] = self::formatEventContentUser($eventContentUserId);
                            }
                        }
                        $eventContent['event_style'] = (int) Util::get($event, 'style');
                        $eventContent['share_text'] = Util::get($event, 'share_text');
                        $feed['event_content'] = $eventContent;
                    }
                    else {
                        unset($feed['event']);
                        $feed['content'] = \Yii::t('app', self::FEED_TIP_EVENT);
                    }
                }
                $eventContent['event_style'] = (int) Util::get($event, 'style');
                $eventContent['share_text'] = Util::get($event, 'share_text');
                $feed['event_content'] = $eventContent;
                $feed['event'] =  $event;
            }
            else {
                $feed['content'] = \Yii::t('app', self::FEED_TIP_EVENT);
            }
            $shareImage = isset($feed['event_content']) ? Util::get($feed['event_content'], 'pic') : $shareImage;
            $shareText = $feed['type'] == self::FEED_TYPE_LIKE_EVENT_CONTENT ?
                '喜欢了'.Util::get($event, 'title').'活动' : '参与'.Util::get($event, 'title').'活动';
        }

        if ($followUserId) {
            $followUserInfo = EchoUser::getInfoById($followUserId);
            if ($followUserInfo) {
                $followUserInfo = EchoUser::processMiddleProfile($followUserInfo, false, true, true);
                $followUserInfo = array_merge($followUserInfo, EchoFamousUser::getFamousTypeLabelById($followUserId, true, Util::get($followUserInfo, 'famous_status')));
                $feed['follow_user'] = $followUserInfo;
            } else {
                $feed['content'] = \Yii::t('app', self::FEED_TIP_FOLLOW_USER);
            }
            $shareImage = isset($feed['follow_user']) ? Util::get($feed['follow_user'], 'avatar') : $shareImage;
            $shareText = isset($feed['follow_user']) ? '关注了'.Util::get($feed['follow_user'], 'name') : $shareText;
        }

        if($tvChannelId) {
            $tvChannelInfo = EchoLiveChannel::getChannelForFeed($tvChannelId);
            if($tvChannelInfo) {
                $feed['tv_channel'] = $tvChannelInfo;
            }
            else {
                $feed['content'] = \Yii::t('app', self::FEED_TIP_TV_CHANNEL);
            }
        }

        if($topicId) {
            $topicInfo = EchoTopic::getTopicForFeed($topicId);
            if($topicInfo) {
                $feed['topic'] = $topicInfo;
            }
            else {
                $feed['content'] = \Yii::t('app', self::FEED_TIP_TOPIC);
            }
        }

        if($gameShareId) {
            $gameShare = self::formatGameShare($gameShareId);
            if($gameShare) {
                $feed['game_share'] = $gameShare;
            }
            else {
                $feed['content'] = \Yii::t('app', self::FEED_TIP_SOUND);
            }
        }

        if($gameSoundId) {
            $gameShare = self::formatGameShare($gameSoundId, 'sound');
            if($gameShare) {
                $feed['game_share'] = $gameShare;
            }
            else {
                $feed['content'] = \Yii::t('app', self::FEED_TIP_SOUND);
            }
        }

        if($echoGroupId) {
            $echoGroup = EchoGroup::takeForFeed($echoGroupId, $currentUserId);
            if ($echoGroup) {
                $feed['echo_group'] = $echoGroup;
                $groupName = Util::get($echoGroup, 'name');
                $groupCreatedUserId = (int) Util::get($echoGroup, 'created_user_id');
                $feed['content'] = $groupCreatedUserId == $publisherId ?
                    '我已开通'.$groupName.'粉丝群啦，快来一起玩吧！':
                    '我已加入'.$groupName.'粉丝群啦，快来一起玩吧！';
            } else {
                $feed['content'] = \Yii::t('app', self::FEED_TIP_ECHO_GROUP);
            }
        }


        $feed['like_num'] = EchoUserActivity::getFieldCount($activity);
        $feed['comment_num'] = (int) Util::get($activity, EchoUserActivity::FIELD_COMMENT_COUNT, 0);
        $feed['relay_num'] = EchoUserActivity::getFieldCount($activity, EchoUserActivity::FIELD_RELAY_COUNT);

        $feed['is_like'] = $currentUserId ? (int) EchoUserActivityLike::isLike($currentUserId, $feed['activity_id']) : 0;
        $feed['is_relay'] = 0;

        $feed['share_info'] = [
            'title' => Util::get($feed['publisher'], 'name'),
            'image' => $shareImage,
            'content' => $content ? : $shareText,
            'url' => self::FEED_SHARE_URL.$feed['activity_id'],
            'enable' => Util::isNewVersion(2018021401, 249) ? 1 : 0,
        ];

        if(isset($feed['sound']['info'])) {
            unset($feed['sound']['info']);
        }

        EchoTranslate::renderTranslateInRow($feed['activity_id'], EchoTranslate::TYPE_ACTIVITY, $feed);

        //针对港澳台，转发数据，自动翻译繁体
        if(Util::isHant()){
            if(isset($feed['origin_content']) && !empty($feed['origin_content'])){
                $feed['origin_content'] = ZhConversion::zh2hant($feed['origin_content']);
            }
        }
    }

    public static function filterFeed(&$feeds, $feed, $k, $activity)
    {
        $currentUserId = (int) Yii::$app->getUser()->getId();
        if(!$feed['publisher'] || !$activity || (isset($feed['echo_group']) && !Util::get($feed['echo_group'], 'name'))) {
            unset($feeds[$k]);
            Util::systemCall(['\\' . get_called_class() . '::deleteFeedByActivityId', $feed['activity_id'], $currentUserId], 'feed_remove_for_fans_router');
        }

        if(isset($feed['sound']) && EchoSound::isShortVideo($feed['sound']) && !Util::supportShortVideoFeed()) {
            unset($feeds[$k]);
        }

        if(
            (YII_ENV == 'prod' && YII_FRONTEND == 'app' && !Util::isNewVersion(20150920, 79) && isset($feed['mv'])) ||
            (YII_ENV == 'prod' && YII_FRONTEND == 'app' && !Util::isNewVersion(20151030, 80) && self::isNewVersionType($feed['type'])) ||
            (YII_ENV == 'prod' && YII_FRONTEND == 'app' && !Util::isNewVersion(20151030, 80) && isset($feed['origin_type']) && self::isNewVersionType($feed['origin_type'])) ||
            (YII_ENV == 'prod' && YII_FRONTEND == 'app' && !Util::isNewVersion(20151114, 82) && ($feed['type'] == self::FEED_TYPE_PROMOTION_ACTIVITY || $feed['type'] == self::FEED_TYPE_EXPRESSION || $feed['type'] == self::FEED_TYPE_ORIGIN_DELETED)) ||
            (YII_ENV == 'prod' && YII_FRONTEND == 'app' && !Util::isNewVersion(20151114, 82) && isset($feed['origin_type']) && ($feed['origin_type'] == self::FEED_TYPE_PROMOTION_ACTIVITY || $feed['origin_type'] == self::FEED_TYPE_EXPRESSION)) ||
            (YII_ENV == 'prod' && YII_FRONTEND == 'app' && !Util::isNewVersion(20151230, 85) && ((isset($feed['type']) && $feed['type'] == self::FEED_TYPE_FOLLOW_USER) || (isset($feed['origin_type']) && $feed['origin_type'] == self::FEED_TYPE_FOLLOW_USER))) ||
            (YII_ENV == 'prod' && YII_FRONTEND == 'app' && !Util::isNewVersion(20160209, 86) && ((isset($feed['type']) && $feed['type'] == self::FEED_TYPE_SHARE_EVENT) || (isset($feed['origin_type']) && $feed['origin_type'] == self::FEED_TYPE_SHARE_EVENT))) ||
            (YII_ENV == 'prod' && YII_FRONTEND == 'app' && ((isset($feed['type']) && $feed['type'] == self::FEED_TYPE_SHARE_TV_CHANNEL) || (isset($feed['origin_type']) && $feed['origin_type'] == self::FEED_TYPE_SHARE_TV_CHANNEL))) ||
            (YII_ENV == 'prod' && YII_FRONTEND == 'app' && !Util::isNewVersion(2016090101, 119) && ((isset($feed['type']) && $feed['type'] == self::FEED_TYPE_SHARE_TOPIC) || (isset($feed['origin_type']) && $feed['origin_type'] == self::FEED_TYPE_SHARE_TOPIC))) ||
            (YII_ENV == 'prod' && YII_FRONTEND == 'app' && !Util::isNewVersion(2016090101, 119) && ((isset($feed['type']) && $feed['type'] == self::FEED_TYPE_SHARE_GAME_SCORE) || (isset($feed['origin_type']) && $feed['origin_type'] == self::FEED_TYPE_SHARE_GAME_SCORE))) ||
            (YII_ENV == 'prod' && YII_FRONTEND == 'app' && !Util::isNewVersion(2016090101, 119) && ((isset($feed['type']) && $feed['type'] == self::FEED_TYPE_SHARE_GAME_SOUND) || (isset($feed['origin_type']) && $feed['origin_type'] == self::FEED_TYPE_SHARE_GAME_SOUND))) ||
            (YII_ENV == 'prod' && YII_FRONTEND == 'app' && !Util::isNewVersion(2016121601, 140) && ((isset($feed['subject_info']) && !empty($feed['subject_info'])))) ||
            (YII_ENV == 'prod' && YII_FRONTEND == 'app' && (!Util::isNewVersion(2017070302, 166) || !Util::isShowFansGroup($currentUserId)) && ((isset($feed['type']) && $feed['type'] == self::FEED_TYPE_SHARE_ECHO_GROUP) || (isset($feed['origin_type']) && $feed['origin_type'] == self::FEED_TYPE_SHARE_ECHO_GROUP)))
        ) {
            unset($feeds[$k]);
        }
    }
    
    protected static function formatSound($soundId) 
    {
        $sound = EchoSound::getInfoById($soundId);
        
        return EchoSound::processSoundForFeed($sound);
    }
    
    protected static function formatUser($userId)
    {
        $user = EchoUser::getInfoById($userId);

        if(!$user) {
            return [];
        }
        
        $user = EchoUser::processMiniProfile($user);

        return Util::arrayFilter($user,
            ['id', 'name', 'avatar', 'avatar_100', 'pay_class', 'pay_status', 'famous_status', 'famous_type', 'famous_icon','is_ready']
        );
    }

    protected static function formatEventContentUser($userId)
    {
        $user = EchoUser::getInfoById($userId);

        if(!$user) {
            return null;
        }

        return [
            'id' => $user['id'],
            'name' => $user['name'],
        ];
    }
    
    protected static function formatContent($content, $soundInfo, $type, $origin)
    {
        switch($type) {
            case self::FEED_TYPE_DEFAULT:
                $result = $content ? : '';
                break;
            case self::FEED_TYPE_UPLOAD_SOUND:
                $result = $content ? : ($soundInfo ? : '');
                break;
            case self::FEED_TYPE_BIRTHDAY:
                $result = $origin == self::FEED_ORIGIN_SELF ? \Yii::t('app', self::FEED_CONTENT_BIRTHDAY_SELF) : \Yii::t('app', self::FEED_CONTENT_BIRTHDAY_OTHER);
                break;
            default:
                $result = $content;
                break;
        }

        return $result;
    }

    protected static function formatLabel($type, $soundId, $label = 'text', $sound = [])
    {
        if($label == 'content') {
            return '';
        }

        $labelArr = [
            'text' => self::FEED_LABEL_TEXT_DEFAULT,
            'icon' => self::FEED_LABEL_ICON_DEFAULT,
            'content' => self::FEED_LABEL_CONTENT_DEFAULT
        ];
        
        $arr = self::labelArr();
        if(array_key_exists($type, $arr)) {
            $labelArr = $arr[$type];
        }

        $sound = $sound ? : self::formatSound($soundId);
        if($soundId && $type == self::FEED_TYPE_DEFAULT) {
            $shareText = self::FEED_LABEL_TEXT_SHARE_SOUND;
            if(EchoSound::isShortVideo($sound)) {
                $shareText = self::FEED_LABEL_TEXT_SHARE_MV;
            }
            $labelArr = [
                'text' => \Yii::t('app', $shareText),
                'icon' => self::FEED_LABEL_ICON_DEFAULT,
                'content' => self::FEED_LABEL_CONTENT_DEFAULT
            ];
        }

        if($soundId && $type == self::FEED_TYPE_LIKE) {
            $likeText = self::FEED_LABEL_TEXT_LIKE_SOUND;
            if(EchoSound::isShortVideo($sound)) {
                $likeText = self::FEED_LABEL_TEXT_LIKE_MV;
            }
            $labelArr = [
                'text' => \Yii::t('app', $likeText),
                'icon' => self::FEED_LABEL_ICON_DEFAULT,
                'content' => self::FEED_LABEL_CONTENT_DEFAULT
            ];
        }

        if($soundId && $type == self::FEED_TYPE_UPLOAD_SOUND) {
            $uploadText = self::FEED_LABEL_TEXT_UPLOAD_SOUND;
            if(EchoUgc::isCoverSong(Util::get($sound, 'cover_song_id'))) {
                $uploadText = self::FEED_LABEL_TEXT_UPLOAD_COVER_SOUND;
            }
            if(Util::get($sound, 'is_star')) {
                $uploadText = self::FEED_LABEL_TEXT_UPLOAD_STAR_SINGER_SOUND;
            }
            if(EchoSound::isShortVideo($sound)) {
                $uploadText = self::FEED_LABEL_TEXT_UPLOAD_SHORT_VIDEO_SOUND;
            }

            $labelArr = [
                'text' => \Yii::t('app', $uploadText),
                'icon' => self::FEED_LABEL_ICON_DEFAULT,
                'content' => self::FEED_LABEL_CONTENT_DEFAULT
            ];
        }

        return $labelArr[$label];
    }

    protected static function formatTail($share, $device)
    {
        return $share ? compact('share') : ($device ? compact('device') : []);
    }

    protected static function formatGameShare($gameStuffId, $type = 'share')
    {
        if($type == 'share') {
            $gameShareData = (new BeatService())->getShareData($gameStuffId);
            $playerCount = (int) $gameShareData['sum_player'];

            return [
                'beat_id' => (int) $gameShareData['beat_id'],
                'remark' => $gameShareData['sound_name'],
                'tag' => $playerCount.'人已加入echoBeat',
                'sound_id' => (int) $gameShareData['share_info']['sound_id'],
                'img' => $gameShareData['sound_pic'],
            ];
        }

        if($type == 'sound') {
            $gameShareData = (new BeatService())->getShareDataBySoundId($gameStuffId);
            $playerCount = (int) $gameShareData['player_count'];

            return [
                'beat_id' => (int) $gameShareData['beat_id'],
                'remark' => $gameShareData['remark'],
                'tag' => $playerCount.'人已加入echoBeat',
                'sound_id' => (int) $gameShareData['sound_id'],
                'img' => $gameShareData['img'],
            ];
        }

        return [];
    }

    protected static function isNewVersionType($type)
    {
        return in_array(
            $type,
            [
                self::FEED_TYPE_LIKE,
                self::FEED_TYPE_FOLLOW_CHANNEL,
                self::FEED_TYPE_COMPOSE_EVENT_CONTENT,
                self::FEED_TYPE_LIKE_EVENT_CONTENT
            ]
        );
    }

    public static function getFeedInfo($activityId)
    {
        $activity = EchoUserActivity::getActivity($activityId);
        if(!$activity) {
            return null;
        }
        $activityUserId = (int) Util::get($activity, EchoUserActivity::FIELD_USER_ID);
        $currentUserId = (int) yii::$app->getUser()->getId();
        $currentUserId = $currentUserId ? : 0;
        $feed = [
            'id' => (int) $activityId,
            'origin' => self::getOrigin($currentUserId, $activityUserId),
            'type' => (int) Util::get($activity, EchoUserActivity::FIELD_TYPE),
            'create_time' => (int) Util::get($activity, EchoUserActivity::FIELD_CREATE_TIME),
            'activity_id' => (int) $activityId,
        ];

        self::getFeedStuff($feed);

        return $feed;
    }

    public static function getNewFeedNum($userId)
    {
        if(!$userId) {
            return 0;
        }

        if(self::getFeedTipsCache()->hHas($userId, self::FEED_TIPS_FIELD_NEW_NUM)) {
            $result = (int) self::getFeedTipsCache()->hGet($userId, self::FEED_TIPS_FIELD_NEW_NUM);
        }
        else {
            $result = 0;
        }

        return $result;
    }

    public static function deleteNewFeedNumKey($userId)
    {
        if (YII_ENV == 'stress_test'){
            return true;
        }

        if(self::getFeedTipsCache()->hHas($userId, self::FEED_TIPS_FIELD_NEW_NUM)) {
            self::getFeedTipsCache()->hDelete($userId, self::FEED_TIPS_FIELD_NEW_NUM);
        }
    }

    public static function addFeedsBefore($userId, $followingId)
    {
        $feedsBefore = self::getFeedsLine($followingId, 1, self::FEED_ORIGIN_SELF, self::FEED_LIMIT_FOR_FOLLOW);
        if($feedsBefore) {
            foreach($feedsBefore as $feedBefore) {
                self::insertByActivityId($userId, $feedBefore['activity_id']);
            }
        }
    }

    public static function getAvatarTip($userId)
    {
        $avatar = '';

        if(self::getFeedTipsCache()->hHas($userId, self::FEED_TIPS_FIELD_AVATAR)) {
            $avatarUserId = (int) self::getFeedTipsCache()->hGet($userId, self::FEED_TIPS_FIELD_AVATAR);
            $avatarUserInfo = EchoUser::getInfoById($avatarUserId);
            $avatarUserInfo = $avatarUserInfo ? EchoUser::processMiniProfile($avatarUserInfo) : [];
            $avatar = $avatarUserInfo ? Util::get($avatarUserInfo, 'avatar_50') : '';
        }

        return $avatar;
    }

    public static function deleteAvatarTip($userId)
    {
        if(self::getFeedTipsCache()->hHas($userId, self::FEED_TIPS_FIELD_AVATAR)) {
            self::getFeedTipsCache()->hDelete($userId, self::FEED_TIPS_FIELD_AVATAR);
        }
    }

    public static function getAdminUserIds()
    {
        $adminUserIdsStr = SysSetting::get('echoapp_feed_admin_user_ids');

        if(!$adminUserIdsStr) {
            return [];
        }

        $adminUserIds = explode(',', $adminUserIdsStr);

        if(!$adminUserIds) {
            return [];
        }

        $result = [];
        foreach($adminUserIds as $adminUserId) {
            $result[] = (int) $adminUserId;
        }

        return $result;
    }

    // create feed for sound moved at backend
    public static function moveSoundFeed($soundId, $createTime = 0)
    {
        $sound = EchoSound::getInfoById($soundId);
        $status = (int) Util::get($sound, 'status');
        if($status == EchoSound::SOUND_STATUS_NORMAL) {
            $userId = (int) Util::get($sound, 'user_id');
            $createTime = $createTime ? : (int) Util::get($sound, 'create_time');
            $oldActivityId = (int) Util::get($sound, 'activity_id');

            if($userId && $createTime) {
                $activityId = EchoUserActivity::createActivity($userId, EchoUserFeed::FEED_TYPE_UPLOAD_SOUND, $soundId, EchoUserBehavior::OBJ_TYPE_SOUND, 0, null, null, 0, 0, $createTime);
                EchoSound::updateActivityIdById($activityId, $soundId);
                self::addEnqueue($userId, self::FEED_TYPE_UPLOAD_SOUND, $soundId, EchoUserBehavior::OBJ_TYPE_SOUND, null, 0, 0, null, $activityId, $createTime);

                if($oldActivityId) {
                    $oldActivity = EchoUserActivity::getActivity($oldActivityId);
                    if($oldActivity) {
                        $oldUserId = (int) Util::get($oldActivity, 'user_id');
                        EchoUserActivity::deleteActivityById($oldActivityId);
                        if($oldUserId) {
                            $newCreateTime = $createTime + 60;
                            $newCreateTime = $newCreateTime > time() ? time() : $newCreateTime;
                            $newActivityId = EchoUserActivity::createActivity($oldUserId, self::FEED_TYPE_RELAY, $soundId, EchoUserBehavior::OBJ_TYPE_SOUND, 0, null, null, $activityId, $activityId, $newCreateTime);
                            self::addEnqueue($oldUserId, self::FEED_TYPE_RELAY, $soundId, EchoUserBehavior::OBJ_TYPE_SOUND, null, $activityId, $activityId, null, $newActivityId, $newCreateTime);
                        }
                    }
                } 
            }
        }
    }

    public static function moveMvFeed($mvId, $createTime = 0)
    {
        $mv = EchoMusicVideo::getApprovedMusicVideoById($mvId);
        $userId = (int) Util::get($mv, 'creator_id');
        $createTime = $createTime ? : (int) Util::get($mv, 'created_at');

        if($userId && $createTime) {
            self::addEnqueue($userId, self::FEED_TYPE_UPLOAD_MV, $mvId, EchoUserBehavior::OBJ_TYPE_MV, null, 0, 0, null, 0, $createTime);
        }
    }

    public static function insertByActivityId($userId, $activityId, $now = false)
    {
        if($userId) {
            $activity = EchoUserActivity::getActivity($activityId);
            if($activity) {
                $createTime = $now ? time() : $activity['create_time'];
                self::createFeed($userId, $activityId, $activity[EchoUserActivity::FIELD_USER_ID], $activity[EchoUserActivity::FIELD_TYPE], $createTime);
            }
        }
    }

    public static function insertRangePromotionActivity($userId, $minActivityId, $maxActivityId)
    {
        $activityIds = EchoFeedPromotion::getRangeActivityIds($minActivityId, $maxActivityId);
        if($activityIds) {
            foreach($activityIds as $activityId) {
                self::insertByActivityId($userId, $activityId);
            }
        }
    }

    public static function getNewFriendFeedAvatar($userId)
    {
        $avatar = '';

        if(self::getFeedTipsCache()->hHas($userId, self::FEED_TIPS_FIELD_FRIEND_AVATAR)) {
            $friendId = (int) self::getFeedTipsCache()->hGet($userId, self::FEED_TIPS_FIELD_FRIEND_AVATAR);
            $friendInfo = EchoUser::getInfoById($friendId);
            $friendInfo = $friendInfo ? EchoUser::processMiniProfile($friendInfo) : [];
            $avatar = $friendInfo ? Util::get($friendInfo, 'avatar_50') : '';
        }

        return $avatar;
    }

    public static function deleteNewFriendFeedKey($userId)
    {
        if (YII_ENV == 'stress_test'){
            return true;
        }

        if(self::getFeedTipsCache()->hHas($userId, self::FEED_TIPS_FIELD_FRIEND_AVATAR)) {
            self::getFeedTipsCache()->hDelete($userId, self::FEED_TIPS_FIELD_FRIEND_AVATAR);
        }
    }

    public static function hasFriendFeed($userId)
    {
        if(self::getFeedTipsCache()->hHas($userId, self::FEED_TIPS_FIELD_HAS_FRIEND)) {
            $result = (int) self::getFeedTipsCache()->hGet($userId, self::FEED_TIPS_FIELD_HAS_FRIEND);
        }
        else {
            if(self::key($userId)->find()->forceIndex('idx_uid_origin_ct_aid')->where(['user_id' => $userId, 'origin' => self::FEED_ORIGIN_FRIEND])->exists()) {
                $result = 1;
            }
            else {
                $result = 0;
            }
            self::hSetFeedTips($userId, self::FEED_TIPS_FIELD_HAS_FRIEND, $result);
        }

        return $result;
    }

    public static function isCreateFeed($userId, $type)
    {
        if (array_key_exists($type, self::$uaSettingTypeMap)) {
            $userActivitySetting = EchoUserExt::getUserActivitySetting($userId);
            $result = $userActivitySetting[EchoUserExt::UA_SETTING_TYPE_PRE.'_'.self::$uaSettingTypeMap[$type]];
        } else {
            $result = 1;
        }

        return $result;
    }

    public static function hSetFeedTips($userId, $field, $value)
    {
        self::getFeedTipsCache()->hSet($userId, $field, $value);
        self::getFeedTipsCache()->autoIncreaseTtl($userId);
    }

    public static function getFeedTipsCache()
    {
        return Util::cache('userFeedTips');
    }

    public static function getFeedsListCache()
    {
        return Util::cache('feedsList');
    }

    public static function getFeedsListCacheKey($userId, $origin)
    {
        return $userId.'_'.$origin;
    }

    public static function feedsListZAdd($key, $createTime, $activityId)
    {
        if(self::getFeedsListCache()->has($key)) {
            self::getFeedsListCache()->zAdd($key, $createTime, $activityId);
            self::getFeedsListCache()->autoIncreaseTtl($key);
            Util::systemCall(['\\' . get_called_class() . '::listKeep', $key], 'feed_list_keep_router');
        }
    }

    public static function feedsListZRem($key, $activityId)
    {
        if(self::getFeedsListCache()->has($key)) {
            self::getFeedsListCache()->zRem($key, $activityId);
            self::getFeedsListCache()->autoIncreaseTtl($key);
        } 
    }

    public static function feedsListRemove($userId, $activityId)
    {
        foreach(self::$allOriginArr as $origin) {
            $key = self::getFeedsListCacheKey($userId, $origin);
            self::feedsListZRem($key, $activityId);
        }
    }

    public static function isAchieve($userId, $activityId)
    {
        $key = self::getFeedsListCacheKey($userId, self::FEED_ORIGIN_NULL);

        return self::getFeedsListCache()->zScore($key, $activityId);
    }

    public static function getOrigin($userId, $followingId)
    {
        if($userId == $followingId) {
            return self::FEED_ORIGIN_SELF;
        }

        if(SpaFollow::checkFriend($userId, $followingId)) {
            return self::FEED_ORIGIN_FRIEND;
        }

        return self::FEED_ORIGIN_NULL;
    }

    protected static function getFollowChannelPromotion($userId, $feeds, $page)
    {
        $result = [];
        $followChannelPromotion = (new ChannelService())->commendSound($userId);
        $commendTime = Util::get($followChannelPromotion, 'commend_time');
        if($commendTime) {
            $result = self::getPromotion($userId, $feeds, $page, $followChannelPromotion, $commendTime, self::FEED_TYPE_PROMOTION_FOLLOW_CHANNEL, 'follow_channel_recommend');
        }

        return $result;
    }

    protected static function getFLSoundPromotion($userId, $feeds, $page)
    {
        $result = [];
        $flSoundPromotion = (new UserService())->friendLikeSound($userId);
        $commendTime = Util::get($flSoundPromotion, 'time');
        $promotion = Util::get($flSoundPromotion, 'data');
        if($commendTime && $promotion) {
            $result = self::getPromotion($userId, $feeds, $page, $promotion, $commendTime, self::FEED_TYPE_PROMOTION_FL_SOUND, 'friend_like_sound');
        }

        return $result;
    }

    protected static function getPromotion($userId, $feeds, $page, $promotion, $commendTime, $type, $feedKey)
    {
        $result = [];
        $feedsMaxCt = $page == 1 ? time() : $feeds[0]['create_time'];
        $feedsMinCt = $feeds[count($feeds) - 1]['create_time'];

        if($commendTime < $feedsMaxCt && $commendTime >= $feedsMinCt) {
            $result[$feedKey] = $promotion;
            $result['create_time'] = $commendTime;
            $result['type'] = $type;
        }
        else {
            $page ++;
            do {
                $nextFeeds = self::getFeedsData($userId, $page);
                $page ++;
            }
            while (!$nextFeeds);
            if($nextFeeds) {
                $nextFeedsMaxCt = Util::get($nextFeeds[0], 'create_time');
                if($nextFeedsMaxCt && $commendTime < $feedsMinCt && $commendTime >= $nextFeedsMaxCt) {
                    $result[$feedKey] = $promotion;
                    $result['create_time'] = $commendTime;
                    $result['type'] = $type;
                }
            }
        }

        return $result;
    }

    protected static function getPromotionPosition($promotionTime, $feeds)
    {
        $position = 0;
        $count = count($feeds);

        if($promotionTime < $feeds[$count - 1]['create_time']) {
            $position = $count;
        }
        else {
            foreach($feeds as $k => $v) {
                if($promotionTime >= $v['create_time']) {
                    $position = $k;
                    break;
                }
            }
        }

        return $position;
    }

    protected static function addPromotion($promotion, $feeds, $feedsCount)
    {
        $result = [];
        $position = self::getPromotionPosition($promotion['create_time'], $feeds);
        for($i =  0; $i <= $feedsCount; $i++) {
            if($i < $position) {
                $result[$i] = $feeds[$i];
            }
            else if( $i == $position) {
                $result[$i] = $promotion;
            }
            else {
                $result[$i] = $feeds[$i - 1];
            }
        }

        return $result ? : $feeds;
    }

    protected static function addPromotionActivities($userId)
    {
        $lastPromotionActivityId = EchoFeedPromotion::getLastActivityId();
        if($lastPromotionActivityId) {
            $userNowPromotionActivityId = self::getUserNowPromotionActivityId($userId);
            if($userNowPromotionActivityId != $lastPromotionActivityId) {
                if($userNowPromotionActivityId < $lastPromotionActivityId) {
                    self::insertByActivityId($userId, $lastPromotionActivityId, true);
                    Util::enqueue(
                        [
                            'user_id' => $userId,
                            'min_activity_id' => $userNowPromotionActivityId,
                            'max_activity_id' => $lastPromotionActivityId
                        ],
                        '',
                        'feed_add_promotion_router'
                    );
                }
                self::getUserNowPromotionActivityIdCache()->set($userId, $lastPromotionActivityId);
            }
        }
    }

    protected static function getUserNowPromotionActivityId($userId)
    {
        return self::getUserNowPromotionActivityIdCache()->has($userId) ?
            (int) self::getUserNowPromotionActivityIdCache()->get($userId) : 0;
    }

    protected static function getUserNowPromotionActivityIdCache()
    {
        return Util::cache('userNowPromotionActivityId');
    }

    public static function processContent(&$content, &$atInfo, &$urlInfo)
    {
        if(YII_ENV == 'stage' || YII_FRONTEND == 'web' || Util::isNewVersion(20151114, 82)) {
            Util::processUrlInContent($content, $urlInfo);
        }
        Util::processAtInContent($content, $atInfo, false);
    }

    public static function addEnqueue(
        $userId,
        $type = 0,
        $objId = 0,
        $objType = 0,
        $content = null,
        $sourceActivityId = 0,
        $originActivityId = 0,
        $imageUrlStr = null,
        $activityId = 0,
        $createTime = 0,
        $objExtId = 0,
        $share = 0
    )
    {
        Util::systemCall(
            [
                '\\app\\library\\service\\FeedService::addFeed', 
                $userId, 
                $objId, 
                $objType, 
                $objExtId, 
                $type, 
                $sourceActivityId, 
                $originActivityId, 
                $content, 
                $imageUrlStr, 
                $createTime, 
                $activityId, 
                $share
            ], 
            'feed_add_router'
        );
    }

    public static function refreshUserActivityCount($userId)
    {
        $count = self::getFeedsCount($userId, self::FEED_ORIGIN_SELF);
        EchoUserExt::updateUserExt($userId, ['user_activity_count' => $count]);
        Util::enqueue(['user_id' => $userId], '', 'user_info_view_router');

        return $count;
    }

    public static function userMerge($masterUserId, $slaveUserId, $limit = 10)
    {
        $maxPage = self::FEED_MERGE_ACHIEVE_LIMIT / $limit;
        $page = 1;

        do {
            $feeds = self::getFeedsLine($slaveUserId, $page, self::FEED_ORIGIN_NULL, $limit);
            if($feeds) {
                foreach($feeds as $feed) {
                    $activity = EchoUserActivity::getActivity($feed['activity_id']);
                    if($activity) {
                        $activityUserId = $activity[EchoUserActivity::FIELD_USER_ID] == $slaveUserId ? $masterUserId : $activity[EchoUserActivity::FIELD_USER_ID];
                        self::createFeed($masterUserId, $feed['activity_id'], $activityUserId, $activity[EchoUserActivity::FIELD_TYPE], $activity[EchoUserActivity::FIELD_CREATE_TIME]);
                        self::deleteFeedByActivityId($feed['activity_id'], $slaveUserId);
                    }
                }
            }
            $page ++;
        }
        while ($page <= $maxPage);

        self::refreshUserActivityCount($masterUserId);
    }

    public static function listKeep($key)
    {
        if(YII_ENV == 'stress_test' || Util::isStressTest()) {
            return;
        }

        $count = self::getFeedsListCache()->zCard($key);
        if($count >= self::FEED_CACHE_COUNT + self::FEED_CACHE_OUT_COUNT) {
            $offset = self::FEED_CACHE_OUT_COUNT - 1;
            $outActivityIdsArr = self::getFeedsListCache()->zRange($key, 0, $offset, false);
            if($outActivityIdsArr) {
                foreach($outActivityIdsArr as $v) {
                    self::getFeedsListCache()->zRem($key, $v);
                }
            }

            $keyArr = explode('_', $key);
            $userId = (int) $keyArr[0];
            $origin = (int) $keyArr[1];
            Util::systemCall(['\\' . get_called_class() . '::listKeepDb', $userId, $origin], 'feed_list_keep_db_router');
        }
    }

    public static function listKeepDb($userId, $origin = self::FEED_ORIGIN_NULL)
    {
        $db = self::key($userId)->getDb();
        $tableName = self::key($userId)->tableName();

        if($origin == self::FEED_ORIGIN_NULL) {
            $countSql = "SELECT count(*) as count FROM {$tableName} FORCE INDEX(idx_uid_origin_ct_aid) WHERE user_id = {$userId} AND origin = 0";
            $count = $db->createCommand($countSql)->queryAll();
            $count = $count[0]['count'];
        }
        else {
            $count = self::FEED_CACHE_COUNT + 1;
        }

        if($count > self::FEED_CACHE_COUNT) {
            self::setFeedKeepUsers($userId, $origin);
        }
    }

    public static function setFeedKeepUsers($userId, $origin)
    {
        $uoStr = implode('_', [$userId, $origin]);
        self::getFeedKeepCache()->sAdd(self::FEED_KEEP_USERS_KEY, $uoStr);
    }

    public static function getFeedKeepCache()
    {
        return Util::cache('feedKeepUsers');
    }

    public static function getUoKey($userId, $origin)
    {
        return implode('_', [$userId, $origin]);
    }

    public static function implodeUoStr($uoStr)
    {
        $uoArr = explode('_', $uoStr);
        $userId = (int) $uoArr[0];
        $origin = (int) $uoArr[1];

        return compact('userId', 'origin');
    }
}
