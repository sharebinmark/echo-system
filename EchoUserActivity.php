<?php

namespace app\models;

use app\library\ModelTrait;
use app\library\queue\QueueClient;
use app\library\Util;
use app\models\base\BaseEchoUserActivity;
use app\models\echofamous\EchoMusicVideo;
use yii;

class EchoUserActivity extends BaseEchoUserActivity
{
    use ModelTrait;

    const FIELD_RELAY_COUNT = 'relay_count';
    const FIELD_COMMENT_COUNT = 'comment_count';
    const FIELD_LIKE_COUNT = 'like_count';
    const FIELD_CREATE_TIME = 'create_time';
    const FIELD_CONTENT = 'content';
    const FIELD_USER_ID = 'user_id';
    const FIELD_STATUS = 'status';
    const FIELD_TYPE = 'type';
    const FIELD_SOUND_ID = 'sound_id';
    const FIELD_MV_ID = 'mv_id';
    const FIELD_CHANNEL_ID = 'channel_id';
    const FIELD_EVENT_CONTENT_ID = 'event_content_id';
    const FIELD_EVENT_ID = 'event_id';
    const FIELD_SOURCE_ACTIVITY_ID = 'source_activity_id';
    const FIELD_ORIGIN_ACTIVITY_ID = 'origin_activity_id';
    const FIELD_FOLLOW_USER_ID = 'follow_user_id';
    const FIELD_EXPRESS_ID = 'express_id';
    const FIELD_TV_CHANNEL_ID = 'tv_channel_id';
    const FIELD_TOPIC_ID = 'topic_id';
    const FIELD_GAME_SHARE_ID = 'game_share_id';
    const FIELD_GAME_SOUND_ID = 'game_sound_id';
    const FIELD_ECHO_GROUP_ID = 'echo_group_id';
    const FIELD_IMAGE_COUNT = 'image_count';
    const FIELD_AT_INFO = 'at_info';
    const FIELD_URL_INFO = 'url_info';
    const FIELD_TOPIC_INFO = 'topic_info';
    const FIELD_ACTIVITY_ID = 'activity_id';
    const FIELD_ORIGIN = 'origin';

    const STATUS_DISABLE = 0;
    const STATUS_ENABLE = 1;

    const DEFAULT_RELAY_CONTENT = '转播动态';

    static $official_activity_id = array(1, 2);

    static $count_fields_array = array(self::FIELD_RELAY_COUNT, self::FIELD_COMMENT_COUNT, self::FIELD_LIKE_COUNT);

    public static function createActivity(
        $userId,
        $type = EchoUserFeed::FEED_TYPE_DEFAULT,
        $objId = 0,
        $objType = 0,
        $objExtId = 0,
        $content = null,
        $imageUrlStr = null,
        $sourceActivityId = 0,
        $originActivityId = 0,
        $createTime = 0,
        $share = 0
    )
    {
        $obj = new self;
        $obj->user_id = $userId;
        $obj->sound_id = $objId;
        $obj->type = $type;
        $obj->create_time = $createTime ?: time();
        $obj->source_activity_id = $sourceActivityId;
        $obj->content = $content;
        $obj->origin_activity_id = $originActivityId;
        $obj->status = self::STATUS_ENABLE;
        $obj->save();

        if (!$obj->id) {
            Yii::error("[create activity] create activity fail - uid:$userId - oid:$objId - type:$type - content:$content");
            return false;
        }

        $activityId = $obj->id;

        $imageCount = 0;
        if ($imageUrlStr) {
            $imageCount = EchoUserActivityImage::getImageCount($imageUrlStr);
            EchoUserActivityImage::handleImageUrlToCreate($imageUrlStr, $activityId, $userId);
        }
        EchoUserBehavior::create(
            $activityId,
            $userId,
            $type,
            $objType,
            $objId,
            $objExtId,
            $imageCount,
            $content,
            $sourceActivityId,
            $originActivityId,
            $createTime,
            $share
        );

        EchoUserExt::updateUserActivityCount($userId);
        EchoUserActivityLine::create($obj->user_id, $obj->create_time, $obj->id);

        if ($sourceActivityId && $originActivityId) {
            if ($sourceActivityId == $originActivityId) {
                EchoUserActivityRelay::create($originActivityId, $activityId, $userId);
            } else {
                EchoUserActivityRelay::create($sourceActivityId, $activityId, $userId);
                EchoUserActivityRelay::create($originActivityId, $activityId, $userId);
            }
            self::getRelayCache()->sAdd($sourceActivityId, $userId);
        }

        if ($content) {
            Util::enqueue(['user_id' => $userId, 'activity_id' => $activityId, 'content' => $content], '', 'activity_bad_router');
        }

        return $activityId;
    }

    public static function deleteActivityById($id)
    {
        $activity = self::getActivity($id);

        if (!$activity) {
            return false;
        }

        $userId = (int)Util::get($activity, self::FIELD_USER_ID);
        $sourceActivityId = (int)Util::get($activity, self::FIELD_SOURCE_ACTIVITY_ID);
        $originActivityId = (int)Util::get($activity, self::FIELD_ORIGIN_ACTIVITY_ID);

        self::updateStatus($id);
        EchoUserActivityRelay::removeByRelayActivityId($id);
        EchoUserExt::updateUserActivityCount($userId, -1);
        EchoUserFeed::deleteFeedByActivityId($id, $userId);
        EchoRecommendUserActivity::remove($id);

        if ($sourceActivityId && $originActivityId) {
            if ($sourceActivityId == $originActivityId) {
                Util::enqueue(
                    array(
                        'field' => self::FIELD_RELAY_COUNT,
                        'activity_id' => $sourceActivityId,
                        'decrease' => true
                    ), '', 'activity_update_count_router'
                );
                EchoUserActivityRelay::removeByActivityId($originActivityId, $id);
            } else {
                Util::enqueue(
                    array(
                        'field' => self::FIELD_RELAY_COUNT,
                        'activity_id' => $sourceActivityId,
                        'decrease' => true
                    ), '', 'activity_update_count_router'
                );
                Util::enqueue(
                    array(
                        'field' => self::FIELD_RELAY_COUNT,
                        'activity_id' => $originActivityId,
                        'decrease' => true
                    ), '', 'activity_update_count_router'
                );
                EchoUserActivityRelay::removeByActivityId($sourceActivityId, $id);
                EchoUserActivityRelay::removeByActivityId($originActivityId, $id);
            }
        }
    }

    public static function updateCount($field, $id, $decrease = false)
    {
        if (!$field || !$id || !in_array($field, self::$count_fields_array)) {
            return false;
        }

        if ($decrease) {
            $activity = self::getActivity($id);
            if (isset($activity[$field]) && $activity[$field] <= 0) {
                return;
            }
            $sql = "Update echo_user_activity set $field = $field - 1 where id = $id;";
        } else {
            $sql = "Update echo_user_activity set $field = $field + 1 where id = $id;";
        }

        $command = static::getDb()->createCommand();
        $command->setSql($sql);
        $command->execute();

        if ($decrease) {
            EchoUserBehavior::updateCount($field, $id, -1);
        } else {
            EchoUserBehavior::updateCount($field, $id);
        }
    }

    public static function removeSleep($id, $limit = 10000)
    {
        $sql = "delete from echo_user_activity where id < $id order by id asc limit $limit;";
        $command = static::getDb()->createCommand();
        $command->setSql($sql);
        $command->execute();
    }

    public static function updateStatus($id, $status = self::STATUS_DISABLE)
    {
        self::updateAll([self::FIELD_STATUS => $status], ['id' => $id]);
        EchoUserBehavior::updateStatus($id, $status);
    }

    public static function getActivity($id)
    {
        $result = EchoUserBehavior::get($id);
        if (!$result) {
            return [];
        }

        return $result;
    }

    public static function getActivityField($id, $field)
    {
        $activity = self::getActivity($id);

        return Util::get($activity, $field);
    }

    public static function isRelay($userId, $id)
    {
        if (self::getRelayCache()->has($id)) {
            return self::getRelayCache()->sIsMember($id, $userId) ? 1 : 0;
        } else {
            return 0;
        }
    }

    public static function getInfoWithSoundInfoWithIdIndex($ids)
    {
        $data = [];
        foreach ($ids as $id) {
            $activity = self::getActivity($id);
            $pic = null;
            $activity['sound'] = null;
            if ($activity) {
                $activityId = (int)Util::get($activity, self::FIELD_ACTIVITY_ID);
                $activity['id'] = $activityId;
                $soundId = (int)Util::get($activity, self::FIELD_SOUND_ID);
                $expressId = (int)Util::get($activity, self::FIELD_EXPRESS_ID);
                $echoGroupId = (int) Util::get($activity, self::FIELD_ECHO_GROUP_ID);
                $originActivityId = (int)Util::get($activity, self::FIELD_ORIGIN_ACTIVITY_ID);
                $originActivity = self::getActivity($originActivityId);
                $imageCount = $originActivity ?
                    (int)Util::get($originActivity, self::FIELD_IMAGE_COUNT) :
                    (int)Util::get($activity, self::FIELD_IMAGE_COUNT);
                if ($soundId) {
                    $sound = EchoSound::getInfoById($soundId);
                    if ($sound['status'] == EchoSound::SOUND_STATUS_NORMAL) {
                        $activity['sound'] = EchoSound::processMiniFields($sound);
                        $pic = Util::get($activity['sound'], 'pic');
                    }
                }
                if ($expressId) {
                    $expression = EchoSoundExpression::formatSound($expressId);
                    $pic = Util::get($expression, 'pic_100');
                }
                if ($imageCount) {
                    $pic = $originActivityId ?
                        EchoUserActivityImage::getPreviewImage($originActivityId) :
                        EchoUserActivityImage::getPreviewImage($id);
                }
                if($echoGroupId) {
                    $echoGroup = EchoGroup::take($echoGroupId, ['id', 'pic']);
                    $pic = Util::get($echoGroup, 'pic_100');
                }
                if ($pic) {
                    $activity['pic'] = $pic;
                }

                $data[$activityId] = $activity;
            }
        }

        return $data;
    }

    public static function removeCache($id)
    {
        EchoUserBehavior::getCache()->delete($id);
    }

    public static function getRelayCache()
    {
        return Util::cache('userActivityIsRelay');
    }

    public static function syncLucky($luckyUserId, $userId, $content, $imageUrlStr, $soundId, $mvId)
    {
        $params = ['lucky_user_id' => $luckyUserId, 'content' => $content];
        if ($imageUrlStr) {
            $imageUrlArr = explode(',', $imageUrlStr);
            $picsStr = json_encode($imageUrlArr);
            $params += ['pics_str' => $picsStr];
        }
        if ($soundId) {
            $soundInfo = EchoSound::getSoundInfoForLuckyFeed($soundId);
            if ($soundInfo) {
                $soundInfo = json_encode($soundInfo);
                $params += ['obj_id' => $soundId, 'type' => 1, 'info' => $soundInfo];
            }
        }
        if ($mvId) {
            $mvInfo = EchoMusicVideo::getMvInfoForLuckyFeed($mvId);
            if ($mvInfo) {
                $mvInfo = json_encode($mvInfo);
                $params += ['obj_id' => $mvId, 'type' => 2, 'info' => $mvInfo];
            }
        }

        Util::luckyRpc($userId, '/rpc/echo-compose-feed', $params);
    }

    public static function syncLuckyEnqueue($luckyUserId, $userId, $content, $imageUrlStr, $soundId, $mvId)
    {
        Util::systemCall(['\\' . get_called_class() . '::syncLucky', $luckyUserId, $userId, $content, $imageUrlStr, $soundId, $mvId], 'activity_sync_lucky_router');
    }

    public static function getOfficialShowCountCache()
    {
        return Util::cache('officialActivityShowCount');
    }

    public static function getFieldCount($activity, $field = self::FIELD_LIKE_COUNT)
    {
        if (!is_array($activity)) {
            return 0;
        }

        $activityId = Util::get($activity, 'activity_id', 0);
        $userId = Util::get($activity, self::FIELD_USER_ID, 0);
        $fieldCount = (int) Util::get($activity, $field, 0);
        if ($userId == EchoUser::OFFICIAL_USER_ID && self::getOfficialShowCountCache()->hHas($activityId, $field)) {
            $fakeFieldCount = (int) self::getOfficialShowCountCache()->hGet($activityId, $field);
            $fieldCount += $fakeFieldCount;
        }

        return $fieldCount;
    }

    public static function getFakeFieldCount($activity, $field = self::FIELD_LIKE_COUNT)
    {
        if (!is_array($activity)) {
            return 0;
        }

        $fieldCount = (int) Util::get($activity, $field, 0);
        $showFieldCount = self::getFieldCount($activity, $field);
        $fakeFieldCount = $showFieldCount - $fieldCount;

        return $fakeFieldCount > 0 ? $fakeFieldCount : 0;
    }

    public static function getIdsByUserId($userId)
    {
        return self::find()
            ->select('id')
            ->where(['user_id' => $userId, 'status' => self::STATUS_ENABLE])
            ->orderBy('id desc')
            ->limit(EchoUserFeed::FEED_CACHE_COUNT)
            ->all();
    }
}
