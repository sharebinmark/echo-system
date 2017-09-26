<?php

namespace app\library;
use app\library\constant\EchoGlobalConstant;
use app\library\IpIpNet\IpIpNet;
use app\library\qiniu\QiniuClient;
use app\library\queue\QueueClient;
use app\library\redis\RedisCache;
use app\library\redis\RedisContainer;
use app\library\utils\StringUtil;
use app\models\EchoAutoCommend;
use app\models\EchoChooseFansGroup;
use app\models\EchoGroup;
use app\models\EchoLanguage;
use app\models\EchoRpcAuthUser;
use app\models\EchoUser;
use app\models\EchoUserGroup;
use app\models\SysSetting;
use app\models\KeyValue;
use app\models\SpaFollow;
use myYii\ServiceException;
use Yii;
use yii\helpers\StringHelper;

class Util {

    const CONVERT_PARAM_KEY = 13816772314;
    const CONVERT_62_INDEX	= 'cCWwiBI79yMbGJ6P8KtS14ZsArgqXRvFzDHxdnONpTLfQUajkolhu2VYm5Ee03'; //62进制索引序列
    const CONVERT_36_INDEX	= '9ext25fkab87wmv4norh3cdyzu0igs6lpqj1'; //不包含大写字母的36进制索引序列
    const CONVERT_36_INDEX_CLIENT_ID	= 'hza2y591l0fmou7jntwbs4cqxgei8r6vpd3k'; //lean cloud的clientId专用 36进制
    const CONVERT_36_INDEX_GIFT_VOUCHER	    = 'gnvrq5lzuh89bwy6apc4jsefmdk2xt73'; //gift voucher专用 32进制
    const CONVERT_32_INDEX_VOUCHER	= 'vrhj4gs9bwymdk2xt73qef5lz6apcnu8'; //voucher专用 32进制
    // $commonRequestHeaders 这里的配置和 http://redmine.kibey.com/projects/echo/wiki/_echo%E6%96%B0%E7%89%88%E5%85%A8%E5%B1%80%E5%8F%82%E6%95%B0%EF%BC%88%E7%AD%BE%E5%90%8D%E7%B3%BB%E7%BB%9F%EF%BC%89_ 表格是对应的
    // 不要随便修改 $commonRequestHeaders，如果需要修改，需要和前端协商
    static $commonRequestHeaders = ['sn', 'c', 'v', 'vs', 'av', 'uuid', 'tk', 'dt', 'net', 'at', 'cd' ];
    const CLIENT_WEB = 1;
    const CLIENT_ANDROID = 2;
    const CLIENT_IOS = 3;
    const CLIENT_WINDOWS = 4;
    const UNIVERSAL_SIGN = 'b679c23816cb5e4c589135ef1d1d35ef';

    const DREAM_KEY = 'dream';
    const MAN_KEY = 'man';
    const LEAN_CLOUD_KEY = 'lean_cloud';
    const YUN_TONG_XUN_KEY = 'yun_tong_xun';
    const DREAM_PROMOTION_KEY = 'dream_promotion';

    const MERGE_INCREASE_QUEUE_COUNT = 20;
    const MERGE_INCREASE_INTERVAL_SECOND_60 = 60;

    static $mergeIncreaseIntervalList = [
        self::MERGE_INCREASE_INTERVAL_SECOND_60,
    ];

    const GO_API_PKEY = 'ff17bcbf76ed6b47908850f66169bfc0';

    static $oldDreamErrorCodes = ['-1', '-2', '-10', '-11', '-12', '-13', '-14', '-101', '-102', '-103', '-200', '-999', '-10001', '-10002', '-10003', '-10004', '-10005', '-10006', '-10007', '-10008', '-10009', '-10010', '-10011', '-10012', '-10013', '-10014', '-10015', '-10016', '-10017', '-10018', '-10019', '-10021', '-10022', '-10023', '-10024', '-10025', '-10026', '-10027', '-10028', '-10029', '-10030', '-10031', '-10032', '-10033'];
    static $dreamErrorCodes = ['-1', '-12', '-999', '-10001', '-10002', '-10003', '-10011','-10015', '-10019', '-10029', '-10057', '-10123', '-10124', '-10125', '-10126', '-10252', '-10253', '-10254'];

    static $allSmsChannels = [
        self::DREAM_KEY => 'dreamSendSMS',
        self::MAN_KEY => 'manSendSMS',
        self::LEAN_CLOUD_KEY => 'leanCloudSendSMS',
        self::YUN_TONG_XUN_KEY => 'yunTongXunSendSMS',
    ];

    public static function get($array, $key, $default = null) {
        return ($array && isset($array[$key])) ? $array[$key] : $default;
    }

    public static function arrayColumn(array $array, $column_key, $index_key = null) {
        if(function_exists('array_column')) {
            return array_column($array, $column_key, $index_key);
        }
        $result = [];
        foreach($array as $arr) {
            if(! is_array($arr)) {
                continue;
            }
            if($column_key === null) {
                $value = $arr;
            } else {
                $value = $arr[$column_key];
            }
            if($index_key === null) {
                $result[] = $value;
            } else {
                $key = $arr[$index_key];
                $result[$key] = $value;
            }
        }
        return $result;
    }

    /**
     * @param array $array
     * @param array $columnKeys column keys used to reserve the specified columns
     * @param mixed $isNotMultipleOrIndexKey true to indicate that $array is one assoc record, false to use number-based keys, null to keep original keys, otherwise will use key $eachElement[$indexKey]
     */
    public static function arrayColumns(array $array, array $columnKeys, $isNotMultipleOrIndexKey = false, $extract1stOne = false) {
        if($isNotMultipleOrIndexKey === true) {
            $ret = [];
            foreach($columnKeys as $key) {
                if($array && isset($array[$key])) {
                    $ret[$key] = $array[$key];
                }
            }
            return $ret;
        }
        $indexKey = $isNotMultipleOrIndexKey;
        $ret = [];
        if($extract1stOne) {
            $columnKeys = current($columnKeys);
        }
        foreach($array as $i => $a) {
            if($extract1stOne) {
                $element = static::get($a, $columnKeys);
            } else {
                $element = [];
                foreach($columnKeys as $key) {
                    if($a && isset($a[$key])) {
                        $element[$key] = $a[$key];
                    }
                }
            }

            if($indexKey === false) {
                $ret[] = $element;
            } else if($indexKey === null) {
                $ret[$i] = $element;
            } else if(isset($a[$indexKey])) {
                $ret[ $a[$indexKey] ] = $element;
            }
        }
        return $ret;
    }

    // 来源
    public static function getSource($type = 'int')
    {
        // source 'web','webmobile','android','iphone'
        // 0：网站；1：手机网页版；2：android；3：ios';4 windows
        $source = array(
            'web', 'wap', 'android', 'ios', 'windows'
        );
        $result = self::agent()['src'];
        if ($type === 'int') {
            $result = array_search($result, $source);
        }
        return $result;
    }

    public static function agent()
    {
        static $ret = null;
        if ($ret === null) {
            $ret = self::_agent();
        }
        return $ret;
    }

    protected static function _agent()
    {
        if(self::signEnabled()) {
            $c = self::getClientAppEnv('c', true);
            $src = 'web';
            if($c === self::CLIENT_ANDROID) {
                $src = 'android';
            } else if($c === self::CLIENT_IOS) {
                $src = 'ios';
            } else if ($c === self::CLIENT_WINDOWS) {
                $src = 'windows';
            } else if (self::isMobileRequest()) {
                $src = 'wap';
            }

            $newRet = array(
                'all'          => Util::get($_SERVER, 'HTTP_USER_AGENT', ''),
                'src'          => $src,
                'version_str'  => self::getClientAppEnv('vs', false, ''),
                'version_num'  => self::getClientAppEnv('v', false, ''),
                'device_token' => $src == 'ios' ? self::getClientAppEnv('uuid') : self::getClientAppEnv('dt'),
            );

            if ($src != 'ios') {
                return $newRet;
            }
        }

        //echo ios 2.9 20150707;(iPhone,iPhone OS8.4);IDFA 0F1725DD-FB2B-471D-83BC-84231FB7CE14
        //Android 4.4.2,samsung SM-N9008V,00000000-4841-58e5-ffff-ffffb53714d7,V2.7,66
        // echo windows 1.0 20160229;(windows,windows OS7);xxxxx-xxxx-xxx-xxx-xx

        //h5 agent
        //Mozilla/5.0 (iPhone; CPU iPhone OS 8_3 like Mac OS X) AppleWebKit/600.1.4 (KHTML, like Gecko) Mobile/12F70 {echo ios 2.9 20150707;(iPhone,iPhone OS8.4);IDFA 0F1725DD-FB2B-471D-83BC-84231FB7CE14}
        //Mozilla/5.0 (Linux; Android 5.1.1; SM-G9280 Build/LMY47X; wv) AppleWebKit/537.36 (KHTML, like Gecko) Version/4.0 Chrome/43.0.2357.121 Mobile Safari/537.36 {Android 5.1.1,samsung SM-G9280,00000000-6a25-af8d-2c6f-ba664a8f0e4d,V3.8-debug,83,352562074326045}



        if(!isset($_SERVER['HTTP_USER_AGENT'])) {
            //No agent
            return array('all' => '', 'src' => 'web', 'version_str' => 'unKnow', 'version_num' => '0', 'device_token' => null);
        }

        $agent = $_SERVER['HTTP_USER_AGENT'];
        $preg = "/{(?P<echo_agent>.+)}/";
        preg_match_all($preg, $agent, $out);

        if (!empty($out['echo_agent'][0])) {
            $agent = $out['echo_agent'][0];
        }

        if (strpos(strtolower($agent),"echo ios") === 0) {
            //iso
            $preg = "/^echo (?P<device>.+) (?P<version_str>.*) (?P<version_num>\d+);.*;IDFA (?P<device_token>.*)$/";
            preg_match_all($preg, $agent, $out);
            if (!isset($out['device'][0])) {
                //兼容老代码
                $preg = "/^echo (?P<device>.+) (?P<version_str>.*) (?P<version_num>\d+);/";
                preg_match_all($preg, $agent, $out);
            }
        } else if (strpos(strtolower($agent),"android") === 0) {
            //android
            $preg = "/^Android[^,]*,.*,(?P<device_token>\w{8}-\w{4}-\w{4}-\w{4}-\w{12}),(?P<version_str>.+?),(?P<version_num>\d+)/";
            preg_match_all($preg, $agent, $out);

            $out['device'][0] = 'android';
            $out['version_num'][0] = empty($out['version_num'][0]) ? yii::$app->request->get('android_v', 0) : $out['version_num'][0];
        }else if (strpos(strtolower($agent),"echo windows") === 0) {
            $preg = "/^echo (?P<device>.+) (?P<version_str>.*) (?P<version_num>\d+);.*;(?P<device_token>.*)$/";
            preg_match_all($preg, $agent, $out);
        }
        $old =  array(
            'all'          => $agent,
            'src'          => !empty($out['device'][0]) ? $out['device'][0] : (self::isMobileRequest() ? 'wap' : 'web'),
            'version_str'  => !empty($out['version_str'][0]) ? $out['version_str'][0] : 'unKnow',
            'version_num'  => !empty($out['version_num'][0]) ? $out['version_num'][0] : 0,
            'device_token' => !empty($out['device_token'][0]) ? $out['device_token'][0] : null,
        );

        if (!isset($newRet)) {
            return $old;
        }

        if ($newRet['device_token'] == $old['device_token']) {
            return $newRet;
        } else {
            $newRet['device_token'] = $old['device_token'];
            return $newRet;
        }
    }

    public static function getAndroidTypeFromAgent()
    {
    	//Android4.4.2,samsung SM-N9008V,00000000-4841-58e5-ffff-ffffb53714d7,V2.7,66
    
        $agentInfo = self::agent();

        if (empty($agentInfo['all'])) {
            return '';
        }
    
    	if ($agentInfo['src'] !== 'android') {
    		return '';
    	}
    
    	$types = explode(',', $agentInfo['all']);
    	array_shift($types);
    	array_pop($types);
    	array_pop($types);
    	array_pop($types);
    	
    	$type = implode(',', $types);
    	
    	return $type;
    }

    /**
     * 获取客户端操作系统版本
     */
    public static function getOsVersion(){

        $agent = Util::get(self::agent(), 'all', '');

        if (empty($agent)) {
            return '';
        }
        
        // $agent = "echo ios 5.7 2016101509;(iPhone,iPhone OS9.3.5);IDFA 8E63F3DF-9EAD-4576-A890-930DB6E94A71";
        // $agent = "Android 4.4.4,Xiaomi MI 3,ffffffff-c72b-5d4d-ffff-ffffdf5f61da,5.7.1alpha,126,860311024853566";
        
        if (strpos(strtolower($agent),"echo ios") === 0) {  //iso
            $preg = "/^echo (?P<device>.+) (?P<version_str>.*) (?P<version_num>\d+);[^,]*,[^,\d]*(?P<os_version>[\d,\.]+)\);IDFA (?P<device_token>.*)$/";
        } else if (strpos(strtolower($agent),"android") === 0) {  //android
            $preg = "/^Android\s+(?P<os_version>[\d,\.]+),.*,(?P<device_token>\w{8}-\w{4}-\w{4}-\w{4}-\w{12}),(?P<version_str>.+?),(?P<version_num>\d+)$/";
        }

        if(!isset($preg)){
            return "";
        }
        
        preg_match_all($preg, $agent, $out);
        return Util::arrayPath($out, 'os_version.0');
    }

    /**
     * 获取IOS IDFA
     */
    public static function getAndroidImei(){

        $agentInfo = self::agent();
        $agent = Util::get($agentInfo, 'all', '');

        if (empty($agent) || $agentInfo['src'] !== 'android' ) {
            return '';
        }
        
        if (strpos(strtolower($agent),"android") === 0) {  //android
            $preg = "/^Android\s+(?P<os_version>[\d,\.]+),.*,(?P<device_token>\w{8}-\w{4}-\w{4}-\w{4}-\w{12}),(?P<version_str>.+?),(?P<imei>\d+)$/";
        }

        if(!isset($preg)){
            return "";
        }
        
        preg_match_all($preg, $agent, $out);
        return Util::arrayPath($out, 'imei.0');
    }

    /**
     * 获取IOS IDFA
     */
    public static function getIosIDFA(){

        $agentInfo = self::agent();
        $agent = Util::get($agentInfo, 'all', '');

        if (empty($agent) || $agentInfo['src'] !== 'ios' ) {
            return '';
        }
        
        if (strpos(strtolower($agent),"echo ios") === 0) {
            $preg = "/^echo (?P<device>.+) (?P<version_str>.*) (?P<version_num>\d+);[^,]*,[^,\d]*(?P<os_version>[\d,\.]+)\);IDFA (?P<idfa>.*)$/";
        }

        if(!isset($preg)){
            return "";
        }
        
        preg_match_all($preg, $agent, $out);
        return Util::arrayPath($out, 'idfa.0');
    }

    public static function isAndroidSupportSystemVersion($notAndroidReturn = true, $notGetVersionReturn = true)
    {
        $agentInfo = self::agent();
        if ($agentInfo['src'] != 'android') {
            return $notAndroidReturn;
        }

        $agent = $agentInfo['all'];
        $agentArray = explode(',', $agent);
        $androidVersion = strtolower($agentArray[0]);

        $preg = "/^android ?(?P<a_s_v>\d)\./";
        preg_match_all($preg, $androidVersion, $out);

        if (isset($out['a_s_v'][0])) {
            if ($out['a_s_v'][0] > 3) {
                return true;
            } else {
                return false;
            }
        } else {
            return $notGetVersionReturn;
        }
    }
    
    /**
     * 只检查提供的参数，如果不填写androidVersion 遇到 android 版本返回false， 反之亦然
     * @param null $iosVersion
     * @param null $androidVersion
     *
     * @return bool
     */
    public static function isNewVersion($iosVersion = null, $androidVersion = null, $includeEqual = false)
    {
        $agent = self::agent();

        if ($iosVersion !== null && $agent['src'] == 'ios' && ($agent['version_num'] > $iosVersion || ($includeEqual && $agent['version_num'] == $iosVersion))) {
            return true;
        }

        if ($androidVersion !== null && $agent['src'] == 'android' && ($agent['version_num'] > $androidVersion || ($includeEqual && $agent['version_num'] == $androidVersion))) {
            return true;
        }

        return false;
    }

    public static function isNewShortVersion($iosShortVersion = null, $androidShortVersion = null, $includeEqual = true)
    {
        static $shortVersions = [
            '6.2.5' => ['android' => '164', 'ios' => '2017042500', ] // TODO maybe change
        ];

        $iosVersion = Util::arrayPath($shortVersions, "$iosShortVersion/ios", null, "/");
        $androidVersion = Util::arrayPath($shortVersions, "$androidShortVersion/android", null, "/");

        return static::isNewVersion($iosVersion, $androidVersion, $includeEqual);
    }

    public static function supportShortVideoFeed() {
        static $supportShortVideoFeed = null;
        if($supportShortVideoFeed === null) {
            $supportShortVideoFeed = Util::isNewShortVersion('6.2.5', '6.2.5') || (Util::getSource('string') == 'web');
        }
        return $supportShortVideoFeed;
    }

    public static function getIosClientVersion()
    {
        $ret = self::agent();

        if ($ret['src'] !== 'ios') {
            return false;
        }

        return $ret['version_num'];
    }

    public static function getAndroidClientVersion()
    {
        $ret = self::agent();

        if ($ret['src'] !== 'android') {
            return false;
        }

        return $ret['version_num'];
    }

    public static function checkIosVersionInReview($notIosReturn = false)
    {
        static $reviewing = null;
        if(static::isCli()) {
            return false;
        }
        $version = self::getIosClientVersion();
        if (!$version) {
            return $notIosReturn;
        }
        if($reviewing !== null) {
            return $reviewing;
        }

        $reviewing = false;
        $reviewVersion = SysSetting::getReviewIosVersion();
        if(!$reviewVersion) {
            return false;
        }

        $appId = self::getAppId();
        if (!$appId) {
            return false;
        }

        if (array_search($appId . ':' . $version, $reviewVersion) !== false){
            $reviewing = true;
        }
        return $reviewing;
    }

    public static function getClientIp($defaultIp = '127.0.0.1') {
        return isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : $defaultIp;
    }

    public static function guid() {
        $charid = strtoupper(md5(uniqid(mt_rand(), true)));
        $hyphen = chr(45);// "-"
        $uuid = chr(123)
            . substr($charid, 0, 8) . $hyphen
            . substr($charid, 8, 4) . $hyphen
            . substr($charid, 12, 4) . $hyphen
            . substr($charid, 16, 4) . $hyphen
            . substr($charid, 20, 12)
            . chr(125);
        return str_replace(array('{', '}'), '', $uuid);
    }

    /**
     * @param string $bucket
     * @return \app\library\redis\RedisCache
     */
    public static function cache($bucket = 'persistentInfo', $useMaster = false) {
        return Yii::$app->redis->getCache($bucket, $useMaster ? RedisContainer::CONN_TYPE_MASTER : null);
    }

    public static function pset($key, $value, $cache = null) {
        if($cache === null) {
            $cache = static::cache('persistentInfo');
        } else if(is_string($cache)) {
            $cache = static::cache($cache);
        }
        $value = json_encode($value, JSON_UNESCAPED_SLASHES /*| JSON_UNESCAPED_UNICODE*/);
        return $cache->set($key, $value);
    }

    public static function pget($key, $cache = null) {
        if($cache === null) {
            $cache = static::cache('persistentInfo');
        } else if(is_string($cache)) {
            $cache = static::cache($cache);
        }
        $value = $cache->get($key);
        if($value !== false) {
            return json_decode($value, true);
        }
        return false;
    }

    public static function pdelete($key, $cache = null) {
        if($cache === null) {
            $cache = static::cache('persistentInfo');
        } else if(is_string($cache)) {
            $cache = static::cache($cache);
        }
        return $cache->delete($key);
    }

    public static function acquireExclusiveLock($exclusiveLockKey, $value, $maxLockTime, $cache = null) {
        if($cache === null) {
            $cache = static::cache('exLock');
        }
        /** @var $cache \app\library\redis\RedisCache */
        $acquiredLock = $cache->set($exclusiveLockKey, $value, $maxLockTime, ['nx']);
        // header('X-AcquiredLock-' . $exclusiveLockKey . ': ' . json_encode($acquiredLock));
        return $acquiredLock;
    }

    public static function releaseExclusiveLock($exclusiveLockKey, $value, $cache = null) {
        if($cache === null) {
            $cache = static::cache('exLock');
        }
        /** @var $cache \app\library\redis\RedisCache */
        $valueInCache = $cache->get($exclusiveLockKey);
        if($valueInCache == strval($value)) {
            // if(mt_rand(0, 1)) // 随机模拟删除锁失败的情况
            return $cache->delete($exclusiveLockKey);
        }
        return false;
    }

    public static function zAdd($key, $member, $score, $cache, $maxMembers = null, $descOrder = true) {
        /** @var $cache \app\library\redis\RedisCache */
        $membersToBeRemoved = null;
        $ret = $cache->zAdd($key, $score, $member);
        if($maxMembers) {
            $count = $cache->zCard($key);
            if($count > $maxMembers) {
                if($descOrder) { // desc
//                    var_export($cache->zRange($key, 0, -1, false));
//                    echo ' ' . ($count - $maxMembers - 1) . ' ';
                    $cache->zRemRangeByRank($key, 0, $count - $maxMembers - 1);
//                    var_export($cache->zRange($key, 0, -1, false)); echo "\n\n";
                } else {
//                    var_export($cache->zRange($key, 0, -1, false));
//                    echo ' ' . ($maxMembers - 1) . ' ';
                    $cache->zRemRangeByRank($key, $maxMembers, -1);
//                    var_export($cache->zRange($key, 0, -1, false)); echo "\n\n";
                }
            }
        }
        return $ret;
    }

    public static function zRange($key, $offset, $limit, $cache, $descOrder = true) {
        /** @var $cache \app\library\redis\RedisCache */
        if($descOrder) {
            return $cache->zRevRange($key, $offset, $offset + $limit - 1, false);
        }
        return $cache->zRange($key, $offset, $offset + $limit - 1, false);
    }

    public static function mset($key, $value, $expire = null, $cache = null) {
        if($cache === null) {
            $cache = static::cache('mixedInfo');
        } else if(is_string($cache)) {
            $cache = static::cache($cache);
        }
        $value = json_encode($value, JSON_UNESCAPED_SLASHES /*| JSON_UNESCAPED_UNICODE*/);
        if($expire) {
            return $cache->set($key, $value, $expire);
        }
        if($expire === false) {
            return $cache->delete($key);
        }
        return $cache->set($key, $value);
    }

    public static function mget($key, $cache = null) {
        if($cache === null) {
            $cache = static::cache('mixedInfo');
        } else if(is_string($cache)) {
            $cache = static::cache($cache);
        }
        $value = $cache->get($key);
        if($value !== false) {
            return json_decode($value, true);
        }
        return false;
    }

    public static function mdelete($key, $cache = null) {
        if($cache === null) {
            $cache = static::cache('mixedInfo');
        } else if(is_string($cache)) {
            $cache = static::cache($cache);
        }
        return $cache->delete($key);
    }

    public static function isQiniuUrl($url, $checkCdn = true) {
        $isQiniu = strpos($url, '.qiniucdn.com') !== false || strpos($url, '.qiniudn.com') !== false || strpos($url, '.clouddn.com') !== false || strpos($url, 'hls.echo-tv.app-echo.com') !== false || strpos($url, 'ts.pb.echo-tv.app-echo.com') !== false;
        if($isQiniu) {
            return true;
        }
        if($checkCdn) {
            return strpos($url, '-qn-') !== false;
        }
        return false;
    }

    public static function processPicUrl($imgUrl, $mode = 0, $key = 'pic', $fixKeyOrientation = false)
    {
        $imgUrl = str_ireplace('.qiniudn.com/', '.qiniucdn.com/', $imgUrl);
        $ret = [$key => $imgUrl];
        //TODO::why need process this, we need change upyun.
        if (!empty($imgUrl)) {
            if(static::isQiniuUrl($imgUrl)) {
                if($fixKeyOrientation) {
                    $ret[$key] = $imgUrl . (strpos($imgUrl, '?') !== false ? '&' : '?') . 'imageMogr2/auto-orient/quality/100';
                }
                $fops = 'imageMogr2/auto-orient/quality/100%7CimageView2/' . $mode . '/w/[w]/q/100';
                $postfix = (strpos($imgUrl, '?') !== false ? '&' : '?') . $fops;
                $ret[$key . '_100'] = $imgUrl . str_replace('[w]', '100', $postfix);
                $ret[$key . '_200'] = $imgUrl . str_replace('[w]', '200', $postfix);
                $ret[$key . '_500'] = $imgUrl . str_replace('[w]', '500', $postfix);
                $ret[$key . '_640'] = $imgUrl . str_replace('[w]', '640', $postfix);
                $ret[$key . '_750'] = $imgUrl . str_replace('[w]', '750', $postfix);
                $ret[$key . '_1080'] = $imgUrl . str_replace('[w]', '1080', $postfix);
            } else if (strpos($imgUrl, 'http://img.xiami.net') !== false) {
                try {
                    $preg  = "/^(?P<main_url>http:\/\/.*\/)(?P<pic_id>\d+)(?:_\d)?(?P<suffix>\..+)$/";
                    preg_match_all($preg, $imgUrl, $out);

                    if (empty($out['pic_id'][0])) {
                        $ret[$key]      = $imgUrl;
                        $ret[$key . '_100']  = $imgUrl;
                        $ret[$key . '_200']  = $imgUrl;
                        $ret[$key . '_500']  = $imgUrl;
                        $ret[$key . '_640']  = $imgUrl;
                        $ret[$key . '_750']  = $imgUrl;
                        $ret[$key . '_1080'] = $imgUrl;
                    } else {
                        $ret[$key]     = $out['main_url'][0] . $out['pic_id'][0] . $out['suffix'][0];
                        $ret[$key . '_100'] = $out['main_url'][0] . $out['pic_id'][0] . '_1' .$out['suffix'][0];
                        $ret[$key . '_200'] = $out['main_url'][0] . $out['pic_id'][0] . '_2' . $out['suffix'][0];
                        $ret[$key . '_500'] = $out['main_url'][0] . $out['pic_id'][0] . '_4' . $out['suffix'][0];
                        $ret[$key . '_640'] = $out['main_url'][0] . $out['pic_id'][0] . '_4' . $out['suffix'][0];
                        $ret[$key . '_750'] = $out['main_url'][0] . $out['pic_id'][0] . '_4' . $out['suffix'][0];
                        $ret[$key . '_1080'] = $out['main_url'][0] . $out['pic_id'][0] . $out['suffix'][0];
                    }
                } catch (\Exception $e) {
                    return array();
                }
            } else {
                $outEnv = [];
                $ret[$key . '_100'] = Util::thumbUrl($imgUrl, '100', false, null, 0, $outEnv); // $imgUrl . "!100";
                $ret[$key . '_200'] = Util::thumbUrl($imgUrl, '200', false, null, 0, $outEnv);
                $ret[$key . '_500'] = Util::thumbUrl($imgUrl, '500', false, null, 0, $outEnv);
                $ret[$key . '_640'] = Util::thumbUrl($imgUrl, '640', false, null, 0, $outEnv);
                $ret[$key . '_750'] = Util::thumbUrl($imgUrl, '750', false, null, 0, $outEnv);
                $ret[$key . '_1080'] = Util::thumbUrl($imgUrl, '1080', false, null, 0, $outEnv);
            }
        } else {
            return array();
        }

        return $ret;
    }

    public static function setSession($key, $value = '')
    {
        if(!$key) return ;
        if(isset($_SESSION[$key])) {
            unset($_SESSION[$key]);
        }
        $_SESSION[$key] = $value;
    }

    public static function getSession($key = Null, $default = null)
    {
        if(null == $key) return $_SESSION;
        if(isset($_SESSION[$key])) return $_SESSION[$key];	 
        return $default;
    }

    /**
     * 将下划线转化成驼峰表示法
     *
     * @param $s , hello_word
     *
     * @return string, HelloWord
     */
    public static function convertedToCamelCase($s)
    {
        return preg_replace_callback('/(^|_)(?<char>([a-z]||[0-9]))/', create_function('$data', 'return ucfirst($data[\'char\']);'), strtolower($s));
    }

    /**
     * 将驼峰表示法转换成下划线命名法
     *
     * @param $s, HelloWord
     *
     * @return string, hello_word
     */
    public static function convertedToUnderscore($s)
    {
        return strtolower(preg_replace('/(?<!\b)(?=[A-Z])/', "_", $s));
    }

    public static function convertedKeysToUnderscore($array)
    {
        $ret = [];
        foreach ($array as $key => $value) {
            $ret[self::convertedToUnderscore($key)] = $value;
        }

        return $ret;
    }

    public static function executeSql($sql, $params = array(), $forceMaster = false, $db = null)
    {
        if($db === null) {
            $db = Yii::$app->db;
        } else if(is_string($db)) {
            $db = Yii::$app->$db;
        }

        $result = false;
        $originalEnableSlaves = $db->enableSlaves;
        $db->enableSlaves = ! $forceMaster;
        try {
            // Yii::error('test error message: sql is ' . $sql . ', params are ' . json_encode($params));
            if ($params && preg_match_all('/:\w++/', $sql, $fields)) {
                $fields = array_flip($fields[0]);
                foreach (array_keys($params) as $f) {
                    $hasColon = $f[0] === ':';
                    $fieldWithColon = $hasColon ? $f : (':' . $f);
                    if (!isset($fields[$fieldWithColon])) { // unset unnecessary params
                        unset($params[$f]);
                    } else if(! $hasColon) { // if field does not start with :, fix it
                        $params[$fieldWithColon] = $params[$f];
                        unset($params[$f]);
                    }
                }
            }
            if (preg_match('/^\s*+(?:select|show|desc)/i', $sql)) {
                $result = $db->createCommand($sql, $params)->queryAll();
            } else {
                $result = $db->createCommand($sql, $params)->execute();
            }
        } catch (\Exception $e) {
            $db->enableSlaves = $originalEnableSlaves;
            throw $e;
        }
        $db->enableSlaves = $originalEnableSlaves;
        return $result;
    }

    public static function executeSqlFromMaster($sql, $params = array(), $db = null) {
        return static::executeSql($sql, $params, true, $db);
    }

    public static function rsaEncrypt($plainText, $privateKey, $encryptAsBase64 = true) {
        $pi_key =  openssl_pkey_get_private($privateKey); //这个函数可用来判断私钥是否是可用的，可用返回资源id Resource id
        $encrypted = false;
        $ok = openssl_private_encrypt($plainText, $encrypted, $pi_key); //私钥加密
        if(! $ok) {
            return false;
        }
        return $encryptAsBase64 ? base64_encode($encrypted) : $encrypted;
    }

    public static function rsaDecrypt($cipherText, $publicKey, $decryptFromBase64 = true) {
        $pu_key = openssl_pkey_get_public($publicKey); //这个函数可用来判断公钥是否是可用的
        if($decryptFromBase64) {
            $cipherText = base64_decode($cipherText);
        }
        $decrypted = false;
        $ok = openssl_public_decrypt($cipherText, $decrypted, $pu_key); //私钥加密的内容通过公钥可用解密出来
        return $ok ? $decrypted : false;
    }

    public static function aesCbcEncrypt($plainText, $privateKey, $encryptAsBase64 = true, $iv = '0123456789012345') {
        $encrypted = mcrypt_encrypt(MCRYPT_RIJNDAEL_128, $privateKey, $plainText, MCRYPT_MODE_CBC, $iv);
        if($encrypted === false) {
            return false;
        }
        return $encryptAsBase64 ? base64_encode($encrypted) : $encrypted;
    }

    public static function aesCbcDecrypt($cipherText, $privateKey, $decryptFromBase64 = true, $iv = '0123456789012345') {
        if($decryptFromBase64) {
            $cipherText = base64_decode($cipherText);
        }
        return mcrypt_decrypt(MCRYPT_RIJNDAEL_128, $privateKey, $cipherText, MCRYPT_MODE_CBC, $iv);
    }

    /**
     * @param $input
     * @param $key
     * @param $hexadecimal
     * @see https://github.com/stevenholder/PHP-Java-AES-Encrypt
     *
     * @return string
     */
    public static function aesEncrypt($input, $key, $hexadecimal = false)
    {
        //php7中不建议使用 mcrypt 这里抑制这个警告
        $data = @static::aesEncryptRaw($input, $key);
        $data = $hexadecimal ? bin2hex($data) : base64_encode($data);
        return $data;
    }

    public static function aesEncryptRaw($input, $key) {
        //php7中 mcrypt 将会从核心中删除所以会报过时警告现在屏蔽它
        $size  = @mcrypt_get_block_size(MCRYPT_RIJNDAEL_128, MCRYPT_MODE_ECB);
        $input = self::pkcs5Pad($input, $size);
        $td    = @mcrypt_module_open(MCRYPT_RIJNDAEL_128, '', MCRYPT_MODE_ECB, '');
        $iv    = @mcrypt_create_iv(@mcrypt_enc_get_iv_size($td), MCRYPT_RAND);
        @mcrypt_generic_init($td, $key, $iv);
        $data = @mcrypt_generic($td, $input);
        @mcrypt_generic_deinit($td);
        @mcrypt_module_close($td);
        return $data;
    }

    private static function pkcs5Pad ($text, $blockSize)
    {
        $pad = $blockSize - (strlen($text) % $blockSize);
        return $text . str_repeat(chr($pad), $pad);
    }

    public static function aesDecrypt($sStr, $sKey, $hexadecimal = false)
    {
        $data = $hexadecimal ? hex2bin($sStr) : base64_decode($sStr);
        //php7中不建议使用 mcrypt 这里抑制这个警告
        $decrypted= @mcrypt_decrypt(
            MCRYPT_RIJNDAEL_128,
            $sKey,
            $data,
            MCRYPT_MODE_ECB
        );
        $dec_s     = strlen($decrypted);
        $padding   = ord($decrypted[$dec_s-1]);
        $decrypted = substr($decrypted, 0, -$padding);
        return $decrypted;
    }

    public static function ipFromHotCity($ip = null, $timeout = 2) {
        if($ip === null) {
            $ip = Yii::$app->request->getUserIP();
        }
        $info = self::getIpInfoByIpIpNet($ip);
        if (!empty($info[0]) && !empty($info[2]) && $info[0] == '中国' && in_array($info[2], ['北京', '上海', '深圳', '广州', '杭州'])) {
            return true;
        } else {
            return false;
        }

//        $ipInBinary = inet_pton($ip);
//        $key = 'geo_info_for_ip_' . bin2hex($ipInBinary);
//        $geoInfo = static::mget($key);
//        if($geoInfo === false) {
//            $geoInfo = static::getGeoInfoForIp($ip, $timeout);
//            static::mset($key, $geoInfo, 86400); // 1 day
//        }
//        $country = static::get($geoInfo, 'country');
//        $city = static::get($geoInfo, 'city');
//        // non-master city
//        if($country === '中国' && $city && ! in_array(preg_replace('/市$/', '', trim($city)), ['北京', '上海', '深圳', '广州', '杭州'])) {
//            return false;
//        }
//        return true;
    }

    public static function isTargetProvinceIp($targetProvince, $ip = null)
    {
        if($ip === null) {
            $ip = Yii::$app->request->getUserIP();
        }
        $info = self::getIpInfoByIpIpNet($ip);
        if (!empty($info[0]) && !empty($info[1]) && $info[0] == '中国' && in_array($info[1], $targetProvince)) {
            return true;
        } else {
            return false;
        }
    }

    public static function ipToChinaStateCode($ip = null)
    {
        if($ip === null) {
            $ip = Yii::$app->request->getUserIP();
        }
        $info = self::getIpInfoByIpIpNet($ip);
        if (!empty($info[0]) && !empty($info[1]) && $info[0] == '中国') {
            return self::getStateCodeByName($info[1]);
        } else {
            return false;
        }
    }

    //ip匹配数组里的任意国家，返回true
    public static function isIpCopyright($ip = null, $countrys = ['美国','英国'], $timeout = 2)
    {
    	if($ip === null) {
    		$request = Yii::$app->request;
    		if ($request instanceof \yii\console\Request) {
    			return false;
    		} else {
    			$ip = $request->getUserIP();
    		}
    	}
    
    	$info = self::getIpInfoByIpIpNet($ip);
    	if (!empty($info[0]) && in_array($info[0], $countrys)) {
    		return true;
    	} else {
    		return false;
    	}
    
    	//        $ipInBinary = inet_pton($ip);
    	//        $key = 'geo_info_for_ip_' . bin2hex($ipInBinary);
    	//        $geoInfo = static::mget($key);
    	//        if($geoInfo === false) {
    	//            $geoInfo = static::getGeoInfoForIp($ip, $timeout);
    	//            static::mset($key, $geoInfo, 86400); // 1 day
    	//        }
    	//        $country = static::get($geoInfo, 'country');
    	//        if($country === '美国') {
    	//            return true;
    	//        }
    	//        return false;
    }

    public static function isDistrictIp($districts, $ip = null)
    {
        if($ip === null) {
            $request = Yii::$app->request;
            if ($request instanceof \yii\console\Request) {
                return false;
            } else {
                $ip = $request->getUserIP();
            }
        }

        $info = self::getIpInfoByIpIpNet($ip);
        $chineseSpecialDistrict = ['香港', '台湾', '澳门'];
        if (!empty($info[1]) && in_array($info[1], $chineseSpecialDistrict)) {
            $currentDistrict = $info[1];
        } else {
            $currentDistrict = $info[0];
        }

        if (in_array($currentDistrict, $districts)) {
            return true;
        } else {
            return false;
        }
    }
    
    public static function ipFromAmerica($ip = null, $timeout = 2)
    {
        if($ip === null) {
            $request = Yii::$app->request;
            if ($request instanceof \yii\console\Request) {
                return false;
            } else {
                $ip = $request->getUserIP();
            }
        }

        $info = self::getIpInfoByIpIpNet($ip);
        if (!empty($info[0]) && $info[0] == '美国') {
            return true;
        } else {
            return false;
        }

//        $ipInBinary = inet_pton($ip);
//        $key = 'geo_info_for_ip_' . bin2hex($ipInBinary);
//        $geoInfo = static::mget($key);
//        if($geoInfo === false) {
//            $geoInfo = static::getGeoInfoForIp($ip, $timeout);
//            static::mset($key, $geoInfo, 86400); // 1 day
//        }
//        $country = static::get($geoInfo, 'country');
//        if($country === '美国') {
//            return true;
//        }
//        return false;
    }

    public static function ipFromForeign($ip = null, $timeout = 2)
    {
        if($ip === null) {
            $ip = Yii::$app->request->getUserIP();
        }

        $info = self::getIpInfoByIpIpNet($ip);
        if (!empty($info[0]) && $info[0] != '中国') {
            return true;
        } else {
            return false;
        }

//        $ipInBinary = inet_pton($ip);
//        $key = 'geo_info_for_ip_' . bin2hex($ipInBinary);
//        $geoInfo = static::mget($key);
//        if($geoInfo === false) {
//            $geoInfo = static::getGeoInfoForIp($ip, $timeout);
//            static::mset($key, $geoInfo, 86400); // 1 day
//        }
//        $country = static::get($geoInfo, 'country');
//        if($country !== '中国') {
//            return true;
//        }
//        return false;
    }

    /**
     * @param $ip
     * @return array
     * array (
            'country' => '中国',
            'country_id' => 'CN',
            'area' => '华东',
            'area_id' => '300000',
            'region' => '山东省',
            'region_id' => '370000',
            'city' => '青岛市',
            'city_id' => '370200',
            'county' => '',
            'county_id' => '-1',
            'isp' => '',
            'isp_id' => '-1',
            'ip' => '103.40.102.71',
        )
     */
    public static function getGeoInfoForIp($ip, $timeout = null) {
        $url = 'http://ip.taobao.com/service/getIpInfo.php?ip=' . $ip;
        if($timeout !== null) {
            $response = @file_get_contents($url, false, stream_context_create(['http' => ['timeout' => $timeout]]));
        } else {
            $response = file_get_contents($url);
        }
        if(! $response) {
            return null;
        }
        $data = json_decode($response, true);
        return $data && static::get($data, 'code') === 0 ? static::get($data, 'data') : null;
    }
    
    public static function processAtInContent(&$content, &$atInfo, $replace = true)
    {
        $contentBack = $content;
//        $content     = preg_quote($content);

        $preg = "/@(?<username>[^@]+?)(?<end> |,|，|$)/";

        preg_match_all($preg, $content, $out);

        if($replace) {
            $content = str_replace('/', '//', $contentBack);
        }

        $usersName = $out['username'];

        $ret = array();
        foreach($usersName as $key => &$name){
            //Check Emoji
            if (preg_match('/[\xf0-\xf7][\x80-\xbf]{3}/', $name)) {
                continue;
            }

            $ret[] = stripslashes($name);
        }

        $usersName = $ret;

        if (empty($usersName)) {
            return ;
        }

        //TODO::add cache
        $usersInfo = EchoUser::find()->where(array('name' => $usersName))->all();

        $map = array();
        foreach($usersInfo as $userInfo) {
            $map[$userInfo->name] = $userInfo->id;
        }

        $newPreg = array();
        foreach($usersName as $key => $name) {
            if (isset($map[$name])) {
                $atInfo[]  = array('name' => $name, 'id' => $map[$name]) ;
                $newPreg[] = '/@' . preg_quote($name) . '/';
            }
        }

        $content = preg_replace($newPreg, '/@', $content);
    }

    public static function getConstellationByDate($date)
    {
        if(!$date) {
            return null;
        }

        $date = explode('-', $date);
        $month = $date[1];
        $day = $date[2];

        if ($month < 1 || $month > 12 || $day < 1 || $day > 31)
        {
            return null;
        }

        $constellations = array(
            EchoGlobalConstant::CONSTELLATION_AQU => EchoGlobalConstant::CONSTELLATION_AQU,
            EchoGlobalConstant::CONSTELLATION_PIS => EchoGlobalConstant::CONSTELLATION_PIS,
            EchoGlobalConstant::CONSTELLATION_ARI => EchoGlobalConstant::CONSTELLATION_ARI,
            EchoGlobalConstant::CONSTELLATION_TAU => EchoGlobalConstant::CONSTELLATION_TAU,
            EchoGlobalConstant::CONSTELLATION_GER => EchoGlobalConstant::CONSTELLATION_GER,
            EchoGlobalConstant::CONSTELLATION_CAN => EchoGlobalConstant::CONSTELLATION_CAN,
            EchoGlobalConstant::CONSTELLATION_LEO => EchoGlobalConstant::CONSTELLATION_LEO,
            EchoGlobalConstant::CONSTELLATION_VIR => EchoGlobalConstant::CONSTELLATION_VIR,
            EchoGlobalConstant::CONSTELLATION_LIB => EchoGlobalConstant::CONSTELLATION_LIB,
            EchoGlobalConstant::CONSTELLATION_SCO => EchoGlobalConstant::CONSTELLATION_SCO,
            EchoGlobalConstant::CONSTELLATION_SAG => EchoGlobalConstant::CONSTELLATION_SAG,
            EchoGlobalConstant::CONSTELLATION_CAP => EchoGlobalConstant::CONSTELLATION_CAP
        );

        $breakDays = array(
            EchoGlobalConstant::CONSTELLATION_AQU => 21,
            EchoGlobalConstant::CONSTELLATION_PIS => 19,
            EchoGlobalConstant::CONSTELLATION_ARI => 21,
            EchoGlobalConstant::CONSTELLATION_TAU => 21,
            EchoGlobalConstant::CONSTELLATION_GER => 22,
            EchoGlobalConstant::CONSTELLATION_CAN => 22,
            EchoGlobalConstant::CONSTELLATION_LEO => 23,
            EchoGlobalConstant::CONSTELLATION_VIR => 24,
            EchoGlobalConstant::CONSTELLATION_LIB => 23,
            EchoGlobalConstant::CONSTELLATION_SCO => 24,
            EchoGlobalConstant::CONSTELLATION_SAG => 23,
            EchoGlobalConstant::CONSTELLATION_CAP => 22
        );

        switch ($month) {
            case 1:
                if($day >= $breakDays[EchoGlobalConstant::CONSTELLATION_AQU]) {
                    $constellation = $constellations[EchoGlobalConstant::CONSTELLATION_AQU];
                }
                else {
                    $constellation = $constellations[EchoGlobalConstant::CONSTELLATION_CAP];
                }
                break;
            case 2:
                if($day >= $breakDays[EchoGlobalConstant::CONSTELLATION_PIS]) {
                    $constellation = $constellations[EchoGlobalConstant::CONSTELLATION_PIS];
                }
                else {
                    $constellation = $constellations[EchoGlobalConstant::CONSTELLATION_AQU];
                }
                break;
            case 3:
                if($day >= $breakDays[EchoGlobalConstant::CONSTELLATION_ARI]) {
                    $constellation = $constellations[EchoGlobalConstant::CONSTELLATION_ARI];
                }
                else {
                    $constellation = $constellations[EchoGlobalConstant::CONSTELLATION_PIS];
                }
                break;
            case 4:
                if($day >= $breakDays[EchoGlobalConstant::CONSTELLATION_TAU]) {
                    $constellation = $constellations[EchoGlobalConstant::CONSTELLATION_TAU];
                }
                else {
                    $constellation = $constellations[EchoGlobalConstant::CONSTELLATION_ARI];
                }
                break;
            case 5:
                if($day >= $breakDays[EchoGlobalConstant::CONSTELLATION_GER]) {
                    $constellation = $constellations[EchoGlobalConstant::CONSTELLATION_GER];
                }
                else {
                    $constellation = $constellations[EchoGlobalConstant::CONSTELLATION_TAU];
                }
                break;
            case 6:
                if($day >= $breakDays[EchoGlobalConstant::CONSTELLATION_CAN]) {
                    $constellation = $constellations[EchoGlobalConstant::CONSTELLATION_CAN];
                }
                else {
                    $constellation = $constellations[EchoGlobalConstant::CONSTELLATION_GER];
                }
                break;
            case 7:
                if($day >= $breakDays[EchoGlobalConstant::CONSTELLATION_LEO]) {
                    $constellation = $constellations[EchoGlobalConstant::CONSTELLATION_LEO];
                }
                else {
                    $constellation = $constellations[EchoGlobalConstant::CONSTELLATION_CAN];
                }
                break;
            case 8:
                if($day >= $breakDays[EchoGlobalConstant::CONSTELLATION_VIR]) {
                    $constellation = $constellations[EchoGlobalConstant::CONSTELLATION_VIR];
                }
                else {
                    $constellation = $constellations[EchoGlobalConstant::CONSTELLATION_LEO];
                }
                break;
            case 9:
                if($day >= $breakDays[EchoGlobalConstant::CONSTELLATION_LIB]) {
                    $constellation = $constellations[EchoGlobalConstant::CONSTELLATION_LIB];
                }
                else {
                    $constellation = $constellations[EchoGlobalConstant::CONSTELLATION_VIR];
                }
                break;
            case 10:
                if($day >= $breakDays[EchoGlobalConstant::CONSTELLATION_SCO]) {
                    $constellation = $constellations[EchoGlobalConstant::CONSTELLATION_SCO];
                }
                else {
                    $constellation = $constellations[EchoGlobalConstant::CONSTELLATION_LIB];
                }
                break;
            case 11:
                if($day >= $breakDays[EchoGlobalConstant::CONSTELLATION_SAG]) {
                    $constellation = $constellations[EchoGlobalConstant::CONSTELLATION_SAG];
                }
                else {
                    $constellation = $constellations[EchoGlobalConstant::CONSTELLATION_SCO];
                }
                break;
            case 12:
                if($day >= $breakDays[EchoGlobalConstant::CONSTELLATION_CAP]) {
                    $constellation = $constellations[EchoGlobalConstant::CONSTELLATION_CAP];
                }
                else {
                    $constellation = $constellations[EchoGlobalConstant::CONSTELLATION_SAG];
                }
                break;
        }

        return $constellation;
    }

    public static function getFeedSoundIdByDate($date)
    {
        $constellation = self::getConstellationByDate($date);

        switch ($constellation) {
            case EchoGlobalConstant::CONSTELLATION_AQU:
                $soundIdsArr = array(144412, 144412);
                break;
            case EchoGlobalConstant::CONSTELLATION_PIS:
                $soundIdsArr = array(351063, 351058);
                break;
            case EchoGlobalConstant::CONSTELLATION_ARI:
                $soundIdsArr = array(350971, 156391);
                break;
            case EchoGlobalConstant::CONSTELLATION_TAU:
                $soundIdsArr = array(350968, 1232693);
                break;
            case EchoGlobalConstant::CONSTELLATION_GER:
                $soundIdsArr = array(1232711, 350957);
                break;
            case EchoGlobalConstant::CONSTELLATION_CAN:
                $soundIdsArr = array(53321, 350976);
                break;
            case EchoGlobalConstant::CONSTELLATION_LEO:
                $soundIdsArr = array(350983, 350981);
                break;
            case EchoGlobalConstant::CONSTELLATION_VIR:
                $soundIdsArr = array(1232755, 350985);
                break;
            case EchoGlobalConstant::CONSTELLATION_LIB:
                $soundIdsArr = array(350990, 350996);
                break;
            case EchoGlobalConstant::CONSTELLATION_SCO:
                $soundIdsArr = array(351018, 351026);
                break;
            case EchoGlobalConstant::CONSTELLATION_SAG:
                $soundIdsArr = array(77065, 351033);
                break;
            case EchoGlobalConstant::CONSTELLATION_CAP:
                $soundIdsArr = array(192313, 196106);
                break;
            default:
                $soundIdsArr = array(77065, 351033);
                break;
        }
        $soundIdKey = array_rand($soundIdsArr);

        return $soundIdsArr[$soundIdKey];
    }

    public static function isJson($str)
    {
    	if ( is_string($str) && strlen($str) ) {
	    	if ( $str{0} == '{' || $str{0} == '[' ) {
	    		return true;
	    	}
    	}
    	return false;
    }

    // 计算身份证校验码，根据国家标准GB 11643-1999
    public static function idcardVerifyNumber($idcard_base){
    	if(strlen($idcard_base) != 17){
    		return false;
    	}
    	//加权因子
    	$factor = array(7,9,10,5,8,4,2,1,6,3,7,9,10,5,8,4,2);
    	//校验码对应值
    	$verify_number_list = array('1','0','X','9','8','7','6','5','4','3','2');
    	$checksum = 0;
    	for($i=0; $i < strlen($idcard_base); $i++){
    		$checksum += substr($idcard_base, $i, 1) * $factor[$i];
    	}
    	$mod = $checksum % 11;
    	$verify_number = $verify_number_list[$mod];
    	return $verify_number;
    }
    
    // 18位身份证校验码有效性检查
    public static function idcardChecksum18($idcard){
    	if(strlen($idcard) != 18) {
    		return false;
    	}
    	$idcard_base = substr($idcard, 0, 17);
    	if(self::idcardVerifyNumber($idcard_base) != strtoupper(substr($idcard, 17, 1))) {
    		return false;
    	} else {
    		return true;
    	}
    }

    /**
     * set multidimensional keys with a value without two many ISSETs for array
     * e.g. arraySet($a, 'a', 'b', 'c', 1) <=> @$a['a']['b']['c'] = 1
     * @param $array
     * @param $key1
     * @param $value
     * @throws \Exception
     */
    public static function arraySet(&$array, $key1, /* $key2, $key3, ...  */ $value) {
        $args = func_get_args();
        $argc = count($args);
        if($argc < 3)
            throw new \Exception("Expect at least 3 parameters, but $argc given.");
        $value = $args[$argc - 1];
        $a = &$array;
        $keyCount = $argc - 2;
        for($i = 1; $i < $keyCount; ++$i) {
            $key = $args[$i];
            if(! isset($a[$key])) {
                $a[$key] = array();
            }
            $a = &$a[$key];
        }
        $a[$args[$i]] = $value;
    }

    /**
     * get the value of multidimensional keys without two many ISSETs for array
     * e.g. arrayGet($a, 'a', 'b', 'c', 1) <=> isset(@$a['a']['b']['c']) ? @$a['a']['b']['c'] : 1
     * @param $array
     * @param $key1
     * @param $value
     * @throws \Exception
     */
    public static function arrayGet($array, $key1, /* $key2, $key3, ...  */ $defaultValue) {
        $args = func_get_args();
        $argc = count($args);
        if($argc < 3)
            throw new \Exception("Expect at least 3 parameters, but $argc given.");
        $defaultValue = $args[$argc - 1];
        $a = $array;
        $keyCount = $argc - 2;
        for($i = 1; $i < $keyCount; ++$i) {
            $key = $args[$i];
            if(! isset($a[$key])) {
                return $defaultValue;
                break;
            }
            $a = $a[$key];
        }
        $key = $args[$i];
        return isset($a[$key]) ? $a[$key] : $defaultValue;
    }

    /**
     * get the value of multidimensional keys without two many ISSETs for array by json-path-like expr
     * e.g. arrayPath($a, 'a->b->c', 1, '->') <=> arrayGet($a, 'a', 'b', 'c', 1) <=> isset(@$a['a']['b']['c']) ? @$a['a']['b']['c'] : 1
     * @param $array
     * @param $path
     * @param null $defaultValue
     * @param string $separator
     * @param null $limit
     * @return mixed
     */
    public static function arrayPath($array, $path, $defaultValue = null, $separator = '.', $limit = null) {
        $args = $limit === null ? explode($separator, $path) : explode($separator, $path, $limit);
        array_unshift($args, $array);
        array_push($args, $defaultValue);
        return call_user_func_array(array(__CLASS__, 'arrayGet'), $args);
    }

    /**
     * set multidimensional keys with a value without two many ISSETs for array by json-path-like expr
     * e.g. arrayPathSet($a, 'a->b->c', 1, '->') <=> arraySet($a, 'a', 'b', 'c', 1) <=> @$a['a']['b']['c'] = 1
     * @param $array
     * @param $path
     * @param null $value
     * @param string $separator
     * @param null $limit
     * @return mixed
     */
    public static function arrayPathSet(& $array, $path, $value, $separator = '.', $limit = null) {
        $args = $limit === null ? explode($separator, $path) : explode($separator, $path, $limit);
        array_unshift($args, null);
        $args[0] = &$array;
        array_push($args, $value);
        return call_user_func_array(array(__CLASS__, 'arraySet'), $args);
    }

    public static function isNight($time = null)
    {
        if (!$time) {
            $time = time();
        }

        $hour = date("H", $time);
        if ($hour < 9) {
            return true;
        }

        return false;
    }

    public static function isCli() {
        static $isCli = null;
        if($isCli === null) {
            $isCli = (php_sapi_name() === 'cli');
        }
        return $isCli;
    }

    /**
     * 缩略图样式化
     */
    public static function thumbUrl($url, $fix = null, $clipCenter = false, $quality = null, $mode = 0, &$outEnv = null) {
        $url = str_ireplace('.qiniudn.com/', '.qiniucdn.com/', $url);
        if($fix === null) {
            return $url;
        }

        if(! $outEnv) {
            //取空间名
            //TODO::chang hard code about bucket name
            $purl=parse_url($url);
            if(! $purl) {
                return $url;
            }
            $host = Util::get($purl, 'host', '');
//        $hosts = explode('.',$purl['host']);
//        $bucket = $hosts[0];
            if(static::isQiniuUrl($host)) {
                $provider = 'qiniu';
            } else if(strpos($host, 'kibey-echo') !== false
                || strpos($host, 'kibey-fair') !== false
                || strpos($host, 'kibey-soundray') !== false
                || strpos($host, 'echo-mx') !== false
                || strpos($host, 'kibey-sys-avatar') !== false
                || strpos($host, 'echo-web-pic') !== false) {
                $provider = 'upyun';
            } else {
                $provider = 'other';
            }
            if(is_array($outEnv)) { // []
                $outEnv['provider'] = $provider;
            }
        } else {
            $provider = Util::get($outEnv, 'provider');
        }

        if($provider === 'qiniu') {
            if($fix !== '') {
                $resize = explode('.', $fix . '');
                $w = $resize && isset($resize[0]) && $resize[0] ? (int)$resize[0] : null;
                $h = $resize && isset($resize[1]) && $resize[1] ? (int)$resize[1] : null;
                if($clipCenter) {
                    $h = $h ?: $w;
                    $fops = $w ? ("imageMogr2/auto-orient/quality/100%7CimageMogr2/thumbnail/!${w}x${h}r/gravity/Center/crop/${w}x${h}/dx/0/dy/0") : '';
                } else {
                    $fops = $w ? ("imageMogr2/auto-orient/quality/100%7CimageView2/$mode/w/$w" . ($h ? "/h/$h" : '')) : '';
                    if($quality !== null) {
                        $fops .= '/q/' . $quality;
                    } else {
                        $fops .= '/q/100';
                    }
                }
            } else {
                $fops = 'imageMogr2/auto-orient/quality/100';
            }

            if(! $fops) {
                return $url;
            }
            return $url . (strpos($url, '?') !== false ? '&' : '?') . $fops;
        } else if($provider === 'upyun') {
            $resize = explode('.', $fix . '');
            $w = $resize && isset($resize[0]) && $resize[0] ? (int)$resize[0] : null;
            if(! $w) {
                return $url;
            }
            $h = $resize && isset($resize[1]) && $resize[1] ? (int)$resize[1] : null;
            $h = $h ?: $w;

            $op = $clipCenter ? 'both' : 'fwfh';

            $fops = "/$op/${w}x$h/unsharp/true";
            if($quality !== null) {
                $fops .= "/quality/$quality/";
            }

            $index = strrpos($url, '!');
            if($index === false) {
                return $url . '!' . $fops;
            }
            return substr($url, 0, $index) . '!' . $fops;
        }
        return $url;
    }

    public static function qiniuAvInfo($audioOrVideoUrl, $arrayPath = null, $lifetime = 0) {
        if(! static::isQiniuUrl($audioOrVideoUrl)) {
            return false;
        }
        $avInfoUrl = $audioOrVideoUrl . (strpos($audioOrVideoUrl, '?') === false ? '?' : '&') . 'avinfo';
        $avInfo = file_get_contents(QiniuClient::instance()->qiniuUrl($avInfoUrl, $lifetime));
        $avInfo = json_decode($avInfo, true);
        if(! $avInfo) {
            return null;
        }
        if($arrayPath === null) {
            return $avInfo;
        }
        return static::arrayPath($avInfo ?: [], $arrayPath);
    }

    public static function isH264($url, $format = null, $lifetime = 0) {
        $data = self::qiniuAvInfo($url, null, $lifetime);
        $streams = static::get($data, 'streams');
        if(! $streams) {
            return false;
        }
        $isH264 = false;
        foreach($streams as $stream) {
            if($stream['codec_type'] === 'video' && $stream['codec_name'] === 'h264') {
                $isH264 = true;
                break;
            }
        }
        if(! $isH264) {
            return false;
        }
        if($format === null) {
            return true;
        }
        $format = strtolower($format);
        $formatName = static::arrayPath($data, 'format.format_name');

        if($format === 'mp4') {
            $formatNames = explode(',', $formatName);
            $formatNames = array_map('strtolower', $formatNames);
            if(array_search('mp4', $formatNames) === false) { // there is no mp4
                return false;
            }
            if(array_search('mov', $formatNames) !== false) { // there is mp4 & mov
                $compatibleBrands = static::arrayPath($data, 'format.tags.compatible_brands', '');
                return stripos($compatibleBrands, 'mp4') !== false;
            }
            return true;
        }
        return $format === $formatName;
    }

    /**
     * @deprecated 兼容老代码，请使用getSource($type = 'str')
     * 判断手机用户客户端
     */
    public static function getDeviceType(){
        $type = 'other';
        $agent = isset($_SERVER['HTTP_USER_AGENT']) ? strtolower($_SERVER['HTTP_USER_AGENT']) : '';

        if(preg_match('/iphone|ios|ipod|ipad/i', $agent)){
            $type = 'ios';
        }

        if(preg_match('/android/i', $agent)){
            $type = 'android';
        }

        return $type;
    }

    /**
     * 判断是否微信
     */
    public static function getIsWeChat(){
        $agent = isset($_SERVER['HTTP_USER_AGENT']) ? strtolower($_SERVER['HTTP_USER_AGENT']) : '';
        $status = false;

        if(strpos($agent, 'micromessenger')){
            $status = true;
        }

        return $status;
    }

    /**
     * 在微信浏览器内获取微信-主版本号
     * @return bool|int  如果是在微信内返回主版本号,不在微信返回false
     */
    public static function getWeChatBrowserVersion()
    {
        preg_match_all("/MicroMessenger\\/[0-9]/",$_SERVER["HTTP_USER_AGENT"],$str);

        if(!isset($str[0][0])){
            return false;
        }
        $wxVersion = explode("/",$str[0][0]);
        if(!isset($wxVersion[1])){
            return false;
        }
        return $wxVersion[1];
    }

    /**
     * 判断一个UTF8字符串的长度
     *
     * @param string $str
     * @param int $unit 1/2/3 中的一个值, default:1
     *           1表示$str每个汉字当做长度1来统计
     *           2表示$str每个汉字当做长度2来统计
     *           3表示$str每个汉字当做长度3来统计
     */
    public static function strlenUtf8($str, $unit=1) {
    	$len = 0;
    
    	$result = self::substrUtf8($str, 0, strlen($str), true);
    	$count = count($result);
    	for ($i = 0; $i < $count; $i++) {
    		$len += ord($result[$i]) > 127 ? $unit : 1;
    	}
    	return $len;
    }

    /**
     * UTF8字符串的子字符串函数
     *
     * @param string $str
     * @param string $start
     * @param string $length
     */
    public static function substrUtf8($str, $start=0, $length=-1, $return_ary=false)
    {
        $len = strlen($str);
        if ($length == -1) {
            $length = $len;
        }
        $r = array();
        $n = 0;
        $m = 0;

        for ($i = 0; $i < $len; $i++) {
            $x = substr($str, $i, 1);
            $a = base_convert(ord($x), 10, 2);
            $a = substr('00000000' . $a, -8);
            if ($n < $start) {
                if (substr($a, 0, 1) == 0) {
                } elseif (substr($a, 0, 3) == 110) {
                    $i += 1;
                } elseif (substr($a, 0, 4) == 1110) {
                    $i += 2;
                }
                $n++;
            } else {
                if (substr($a, 0, 1) == 0) {
                    $r[] = substr($str, $i, 1);
                } elseif (substr($a, 0, 3) == 110) {
                    $r[] = substr($str, $i, 2);
                    $i += 1;
                } elseif (substr($a, 0, 4) == 1110) {
                    $r[] = substr($str, $i, 3);
                    $i += 2;
                } else {
                    $r[] = '';
                }
                if (++$m >= $length) {
                    break;
                }
            }
        }

        return $return_ary ? $r : implode('', $r);
    }

    /**
     * 判断客户端是否是爬虫
     * @return bool
     */
    public static function isWebSpider() {
        if (!isset($_SERVER['HTTP_USER_AGENT'])) {
            return false;
        }
        $userAgent = strtolower($_SERVER['HTTP_USER_AGENT']);
        $spiders = [
            'Googlebot', // Google 爬虫
            'Baiduspider', // 百度爬虫
            'bingbot', 'msnbot', // Bing爬虫
            'Sogou', // 搜狗 @notice SogouMobileBrowser
            'curl', // curl
            'spider', // curl
        ];
        foreach ($spiders as $spider) {
            $spider = strtolower($spider);
            if (strpos($userAgent, $spider) !== false) {
                return true;
            }
        }
        return false;
    }

    /* 判断客户端是否是手机用户
     * @return boolean
     */
    public static function isMobileRequest() {
        static $isMobileRequest = null;
        if ($isMobileRequest !== null) {
            return $isMobileRequest;
        }
        if (!isset($_SERVER['HTTP_USER_AGENT'])) {
            $isMobileRequest = false;
            return $isMobileRequest;
        }

        // 简化 手机的判断,和js中的统一,参考的163
        if (preg_match('/(mobile|mobi|wap|iphone|android|ipad)/i', strtolower($_SERVER['HTTP_USER_AGENT']))) {
            $isMobileRequest = true;
        }

        if (0) {
            $_SERVER['ALL_HTTP'] = isset($_SERVER['ALL_HTTP']) ? $_SERVER['ALL_HTTP'] : '';
            $mobile_browser = '0';
            if (preg_match('/(up.browser|up.link|mmp|symbian|smartphone|midp|wap|phone|iphone|ipad|ipod|android|xoom)/i', strtolower($_SERVER['HTTP_USER_AGENT'])))
                $mobile_browser++;
            if ((isset($_SERVER['HTTP_ACCEPT'])) and (strpos(strtolower($_SERVER['HTTP_ACCEPT']), 'application/vnd.wap.xhtml+xml') !== false))
                $mobile_browser++;
            if (isset($_SERVER['HTTP_X_WAP_PROFILE']))
                $mobile_browser++;
            if (isset($_SERVER['HTTP_PROFILE']))
                $mobile_browser++;
            $mobile_ua = strtolower(substr($_SERVER['HTTP_USER_AGENT'], 0, 4));
            $mobile_agents = array(
                'w3c ', 'acs-', 'alav', 'alca', 'amoi', 'audi', 'avan', 'benq', 'bird', 'blac',
                'blaz', 'brew', 'cell', 'cldc', 'cmd-', 'dang', 'doco', 'eric', 'hipt', 'inno',
                'ipaq', 'java', 'jigs', 'kddi', 'keji', 'leno', 'lg-c', 'lg-d', 'lg-g', 'lge-',
                'maui', 'maxo', 'midp', 'mits', 'mmef', 'mobi', 'mot-', 'moto', 'mwbp', 'nec-',
                'newt', 'noki', 'oper', 'palm', 'pana', 'pant', 'phil', 'play', 'port', 'prox',
                'qwap', 'sage', 'sams', 'sany', 'sch-', 'sec-', 'send', 'seri', 'sgh-', 'shar',
                'sie-', 'siem', 'smal', 'smar', 'sony', 'sph-', 'symb', 't-mo', 'teli', 'tim-',
                'tosh', 'tsm-', 'upg1', 'upsi', 'vk-v', 'voda', 'wap-', 'wapa', 'wapi', 'wapp',
                'wapr', 'webc', 'winw', 'winw', 'xda', 'xda-'
            );
            if (in_array($mobile_ua, $mobile_agents))
                $mobile_browser++;
            if (strpos(strtolower($_SERVER['ALL_HTTP']), 'operamini') !== false)
                $mobile_browser++;
            // Pre-final check to reset everything if the user is on Windows
            if (strpos(strtolower($_SERVER['HTTP_USER_AGENT']), 'windows') !== false)
                $mobile_browser = 0;
            // But WP7 is also Windows, with a slightly different characteristic
            if (strpos(strtolower($_SERVER['HTTP_USER_AGENT']), 'windows phone') !== false)
                $mobile_browser++;
            $isMobileRequest = $mobile_browser > 0;
        }

        return $isMobileRequest;
    }

    static function encodeParam($param)
    {
        $param = $param + self::CONVERT_PARAM_KEY;

        return base64_encode((int) $param);
    }

    static function decodeParam($param)
    {
        return (int) base64_decode($param) - self::CONVERT_PARAM_KEY;
    }

    public static function printArray($array, $excisionKeyValue = ':', $excisionRow = "\n")
    {
        $ret = '';

        foreach ($array as $key => $val) {
            $ret .= $key . $excisionKeyValue . $val . $excisionRow;
        }

        return $ret;
    }

    public static function strLengthForMobile($str = null)
    {
        if(!$str) {
            return 0;
        }

        return (strlen($str) + mb_strlen($str, 'UTF8')) / 2;
    }

    public static function getDownloadUrlForMobile($isDeviceType = false)
    {
        $isWeChat = self::getIsWeChat();
        $androidDownloadUrl = SysSetting::get(SysSetting::getAndroidConfigKey('echoapp_android_downloadurl'));
        $iosDownloadUrl = $isWeChat ? EchoGlobalConstant::IOS_DOWNLOAD_URL_FOR_WECHAT : EchoGlobalConstant::IOS_DOWNLOAD_URL;

        if (preg_match('/\bmicromessenger\b/i', Util::get($_SERVER, 'HTTP_USER_AGENT')) && substr(strrchr($androidDownloadUrl, '.'), 1) == 'apk') {
            $androidDownloadUrl = EchoGlobalConstant::ANDROID_DOWNLOAD_URL_FOR_WECHAT;
        }
        if (preg_match('/\bweibo\b/i', Util::get($_SERVER, 'HTTP_USER_AGENT')) && Util::getDeviceType() == 'ios') {
            $iosDownloadUrl .= "#https://itunes.apple.com/us/app/wei-bo-xiang-ji2.0/id1037689788";
        }

        if($isDeviceType) {
            return self::getDeviceType() == 'ios' ? $iosDownloadUrl : $androidDownloadUrl;
        }

        return array('android_download_url' => $androidDownloadUrl, 'ios_download_url' => $iosDownloadUrl);
    }

    public static function getLuckyDownloadUrlForMobile($isDeviceType = false)
    {
        $isWeChat = self::getIsWeChat();
        $androidDownloadUrl = SysSetting::get('luckyapp_android_downloadurl');
        $iosDownloadUrl = $isWeChat ? EchoGlobalConstant::LUCKY_IOS_DOWNLOAD_URL_FOR_WECHAT : EchoGlobalConstant::LUCKY_IOS_DOWNLOAD_URL;

        if (preg_match('/\bmicromessenger\b/i', Util::get($_SERVER, 'HTTP_USER_AGENT')) && substr(strrchr($androidDownloadUrl, '.'), 1) == 'apk') {
            $androidDownloadUrl = EchoGlobalConstant::LUCKY_ANDROID_DOWNLOAD_URL_FOR_WECHAT;
        }
        if (preg_match('/\bweibo\b/i', Util::get($_SERVER, 'HTTP_USER_AGENT')) && Util::getDeviceType() == 'ios') {
            $iosDownloadUrl .= "#https://itunes.apple.com/us/app/wei-bo-xiang-ji2.0/id1037689788";
        }

        if($isDeviceType) {
            return self::getDeviceType() == 'ios' ? $iosDownloadUrl : $androidDownloadUrl;
        }

        return array('android_download_url' => $androidDownloadUrl, 'ios_download_url' => $iosDownloadUrl);
    }

    public static function getCode($phone, $type = 0, $isOversea = 0, $sendLimitOptions = [])
    {
//        if(Util::getClientIp() == '182.37.61.25') {
//            return true;
//        }

        $rand = rand(111111, 999999);
        self::getCodeCache()->set($phone, $rand);
        $sendContent = "您的「echo回声」注册验证码是{$rand}，如有疑问请私信echo回声官方微博。";
        $template = 'register';
        if($type == EchoGlobalConstant::PASSWORD_CODE) {
            $sendContent = "欢迎再次回到「echo回声」，你的验证码为{$rand}。";
            $template = 'password';
        }
        if($type == EchoGlobalConstant::BIND_MOBILE_CODE) {
            $sendContent = "您的「echo回声」绑定验证码是{$rand}，如有疑问请私信echo回声官方微博。";
            $template = 'bind_mobile';
        }
        if($type == EchoGlobalConstant::CONFIRM_MOBILE_CODE) {
            $sendContent = "您的「echo回声」验证码是{$rand}，如有疑问请私信echo回声官方微博。";
            $template = 'confirm_mobile';
        }
        if($type == EchoGlobalConstant::CONFIRM_MOBILE_CODE_TICKETS) {
            $sendContent = "您的验证码是{$rand}，如有疑问请私信echo回声官方微博。";
            $template = 'confirm_mobile';
        }
        //data log for code
        DataLog::logCodeMark('', '', array(), '', 0, null, DataLog::MARK_CODE, $phone, $rand, $type);

        if($sendLimitOptions !== false) {
            $cache = self::getCodeCache();
            $phoneKey = $phone . '_last_send_time';
            if(time() - (int)$cache->get($phoneKey) < static::get($sendLimitOptions, 'send_interval_per_phone', 30)) { // 同一个手机号发短信间隔时间限制 30秒只能发一次
                throw new ServiceException('你发送得太频繁了，请稍后重试');
            }
            $ipKey = Util::getClientIp() . '_' . date('Y-m-d') . '_send_count';
            if($cache->get($ipKey) > static::get($sendLimitOptions, 'max_send_count_per_ip', 500)) { // IP 限制，一个IP一天最多发500条短信
                //throw new ServiceException('请稍后重试');
            }
            $phoneCountKey = $phone . '_' . date('Y-m-d') . '_phone_send_count';
            if($cache->get($phoneCountKey) > static::get($sendLimitOptions, 'max_send_count_per_phone', 3)) { // 同一个手机号每天只能发送3条短信
                throw new ServiceException('你发送的太多了，请稍后重试');
            }
        }

        $fixedChannel = self::get($sendLimitOptions, 'fixed_channel');
        $fixedChannel = $fixedChannel && in_array($fixedChannel, self::$allSmsChannels) ? $fixedChannel : null;

        if($isOversea) {
            $ret = self::manOverseaSendSMS($phone, $sendContent);
        } else {
            if($fixedChannel) {
                $ret = call_user_func_array(array('self', $fixedChannel), array($phone, $sendContent, $template, array('code' => $rand)));
            }
            else {
                $ret = self::sendSMS($phone, $sendContent, $template, array('code' => $rand));
            }
        }

        if($sendLimitOptions !== false) {
            if($ret) {
                $cache->set($phoneKey, time(), 86400);
                $cache->incr($ipKey, 1);
                $cache->expire($ipKey, 86400);
                $cache->incr($phoneCountKey, 1);
                $cache->expire($phoneCountKey, 86400);
            }
        }

        return $ret;
    }

    public static function validCode($phone, $code)
    {
        //仅去掉+86
        $phone = StringUtil::stripPhoneCountryCode($phone, '0086');

        if(!$phone || !$code) {
            return false;
        }

        /**
         * @var $cache RedisCache
         */
        $cache = self::getCodeCache();
        $errorRetryCountKey = $phone . '_error_retry_count';
        $activationCode = $cache->get($phone);
        $isValid = $activationCode == $code;
        if(! $isValid) {
            $cache->incr($errorRetryCountKey);
            if($cache->get($errorRetryCountKey) >= 5) { // TODO change 5
                $cache->delete($phone);
                $cache->delete($errorRetryCountKey);
            }
        } else {
            $cache->delete($errorRetryCountKey);
        }

        return $isValid;
    }

    public static function sendSMSForContacts($userId, $mobile)
    {
        $content = "您有1位通讯录好友在玩「echo回声」APP，看看是谁？https://app-echo.com/p/$userId TD退订";
        self::dreamPromotionSendSMS($mobile, $content);
    }

    public static function sendSMS($mobile, $content, $template = null, $param = array())
    {
        if (YII_ENV == 'test') {
            return true;
        }

        $channels = self::$allSmsChannels;

        if ($template == null) {
            //两个运营商不支持自定义内容
            if(isset($channels[self::LEAN_CLOUD_KEY])) {
                unset($channels[self::LEAN_CLOUD_KEY]);
            }

            if(isset($channels[self::YUN_TONG_XUN_KEY])) {
                unset($channels[self::YUN_TONG_XUN_KEY]);
            }
        }

        shuffle($channels);
        $ret = false;
        foreach($channels as $channel) {
            $ret = call_user_func_array(array('self', $channel), array($mobile, $content, $template, $param));
            if ($ret) {
                break;
            }
        }

        DataLog::logSendSMSMark(0, '', [], $mobile, (int) $ret, $content);

        return $ret;
    }

    /**
     * 兼职运营后台登录 验证
     *
     * @param $phone
     * @param int $isOversea
     * @param array $sendLimitOptions
     * @return bool|mixed
     * @throws ServiceException
     */
    public static function backendLoginSendSMS($phone, $sendLimitOptions = [])
    {
        $rand = rand(111111, 999999);
        self::getCodeCache()->set($phone, $rand);
        $sendContent = "您的「echo回声」后台登录的验证码是{$rand}，如有疑问请私信echo回声官方微博。";

        /**
         * @var $cache RedisCache
         */
        $cache = self::getCodeCache();
        $phoneKey = $phone . '_last_send_time';
        if(time() - (int)$cache->get($phoneKey) < static::get($sendLimitOptions, 'send_interval_per_phone', 30)) { // 同一个手机号发短信间隔时间限制 30秒只能发一次
            throw new ServiceException('你发送得太频繁了，请稍后重试');
        }

        $isOversea = $phone ? preg_match('/^00\d*$/', $phone) : 0;//判断是否是海外手机号

        if($isOversea) {
            $ret = self::manOverseaSendSMS($phone, $sendContent);
        } else {
            $ret = self::sendSMS($phone, $sendContent, null, array('code' => $rand));
        }

        if($ret) {
            $cache->set($phoneKey, time(), 86400);
        }

        return $ret;
    }

    public static function sendCodeEmail($email, $type = 0, $params = [], $sendLimitOptions = [])
    {
        $rand = rand(111111, 999999);
        $cache = self::getCodeCache();
        $cache->set($email, $rand);

        $key = $email . '_last_send_time';
        if(time() - (int)$cache->get($key) < static::get($sendLimitOptions, 'send_interval_per_phone', 30)) { // 同一个邮箱间隔时间限制 30秒只能发一次
            throw new ServiceException('你发送得太频繁了，请稍后重试');
        }

        if (YII_ENV == 'stage') {
            $params['domain'] = 'http://4.staging-web.app-echo.com';
        } elseif (YII_ENV == 'prod') {
            $params['domain'] = 'http://www.app-echo.com';
        } else {
            $params['domain'] = 'http://www.app-echo.loc';
        }

        $component = \Yii::createObject(array(
            'class' => 'yii\swiftmailer\Mailer',
            'transport' => array(
                'class' => 'Swift_SmtpTransport',
                'host' => 'smtp.exmail.qq.com',
                'username' => 'system@app-echo.com',
                'password' => 'SAfPlaeshHiLZSqk',
                'port' => 465,
                'encryption' => 'ssl',
            ),
        ));


        /*
        $component = \Yii::createObject(array(
            'class' => 'yii\swiftmailer\Mailer',
            'transport' => array(
                'class' => 'Swift_SmtpTransport',
                'host' => 'smtp.exmail.qq.com',
                'username' => 'oa@app-echo.com',
                'password' => 'sdfIK8##21',
                'port' => 465,
                'encryption' => 'ssl',
            ),
        ));
        */

        switch ( $type ) {
            case 0:
                $viewPath = '@app/views/frontend/email/bind_email_validate.php';
                $title = "echo回声邮箱绑定";
                break;
            case 1:
                $viewPath = '@app/views/frontend/email/reset_email_password.php';
                $title = "echo回声邮箱重置密码";
                break;
        }

        $component->compose()
            ->setFrom(['system@app-echo.com' => 'echo回声'])
            ->setTo($email)
            ->setSubject($title)
            ->setHtmlBody(\Yii::$app->view->renderFile($viewPath, $params))
            ->send();

        $cache->set($key, time(), 3600);

        return true;
    }

    /**
     *
     * @param $phone
     * @param $sendContent
     * @param array $sendLimitOptions
     */
    public static function backendExpressSMS($phone, $sendContent, $sendLimitOptions = [])
    {
        if(empty($phone) || empty($sendContent))
        {
            return;
        }
        self::sendSMS($phone, $sendContent, null);
    }

    public static function dreamSendSMS($mobile, $content)
    {
        $msgContent = urlencode($content);
        $url = "http://61.130.7.220:8023/MWGate/wmgw.asmx/MongateSendSubmit?userId=J50157&password=819278&pszMobis=$mobile&pszMsg=$msgContent&iMobiCount=1&pszSubPort=*";
        $response = file_get_contents($url);
        $ret = simplexml_load_string($response);

        if(isset($ret[0]) && !in_array($ret[0], self::$dreamErrorCodes)) {
            return true;
        }

        \Yii::error('send dream sms failed: ' . var_export($ret, true) . ' param:' . var_export(func_get_args(), true));

        return false;
    }

    public static function manSendSMS($mobile, $content)
    {
        //如果您的系统是utf-8,请转成GB2312 后，再提交、
        //请参考 'content'=>iconv( "UTF-8", "gb2312//IGNORE" ,'您好测试短信[XXX公司]'),//短信内容
        header("Content-Type: text/html; charset=UTF-8");

        $flag = 0;
        $params = '';
        $content .= '【启维文化】';
        //要post的数据
        $argv = array(
            'sn' => 'SDK-WSS-010-08552', ////替换成您自己的序列号
            'pwd' => strtoupper(md5('SDK-WSS-010-08552'.'1BB045B-')), //此处密码需要加密 加密方式为 md5(sn+password) 32位大写
            'mobile' => $mobile,//手机号 多个用英文的逗号隔开 post理论没有长度限制.推荐群发一次小于等于10000个手机号
            'content' => urlencode($content),//iconv( "GB2312", "gb2312//IGNORE" ,'您好测试短信[XXX公司]'),//'您好测试,短信测试[签名]',//短信内容
            'ext' => '',
            'stime' => '',//定时时间 格式为2011-6-29 11:09:21
            'msgfmt' => '',
            'rrid' => ''
        );
        //构造要post的字符串
        //echo $argv['content'];
        foreach ($argv as $key=>$value) {
            if ($flag != 0) {
                $params .= "&";
                $flag = 1;
            }
            $params.= $key."="; $params.= urlencode($value);// urlencode($value);
            $flag = 1;
        }
        $length = strlen($params);
        //创建socket连接
        $fp = fsockopen("sdk.entinfo.cn",8061,$errno,$errstr,10) or exit($errstr."--->".$errno);
        //构造post请求的头
        $header = "POST /webservice.asmx/mdsmssend HTTP/1.1\r\n";
        $header .= "Host:sdk.entinfo.cn\r\n";
        $header .= "Content-Type: application/x-www-form-urlencoded\r\n";
        $header .= "Content-Length: ".$length."\r\n";
        $header .= "Connection: Close\r\n\r\n";
        //添加post的字符串
        $header .= $params."\r\n";
        //发送post的数据
        //echo $header;
        //exit;
        fputs($fp,$header);
        $inheader = 1;
        while (!feof($fp)) {
            $line = fgets($fp,1024); //去除请求包的头只显示页面的返回数据
            if ($inheader && ($line == "\n" || $line == "\r\n")) {
                $inheader = 0;
            }
            if ($inheader == 0) {
                // echo $line;
            }
        }
        //<string xmlns="http://tempuri.org/">-5</string>
        $line=str_replace("<string xmlns=\"http://tempuri.org/\">","",$line);
        $line=str_replace("</string>","",$line);
        $result=explode("-",$line);
        // echo $line."-------------";
        if(count($result)>1) {
            $returnText = '发送失败返回值为:'.$line.'。请查看webservice返回值对照表';
            \Yii::error('Send man sms failed, error is ' . var_export($returnText, true) . ' param:' . var_export(func_get_args(), true));
            return false;
        }
        else {
            return true;
        }
    }

    public static function leanCloudSendSMS($mobile, $content, $template, $param = array())
    {
        if (!isset($param['code'])) {
            \Yii::error('send LeanCloud sms failed: code not exist');
            return false;
        }

        $url = "https://api.leancloud.cn/1.1/requestSmsCode";
        $data = [
            'mobilePhoneNumber' => $mobile,
            'template'          => $template,
            'mycode'            => $param['code'],
        ];

        $headers = [];
        $headers[] = "X-AVOSCloud-Application-Id: 7o6h18o5c8u1sd1pg0rp26twq8nvje5pxckcac3m6tayqolq";
        $headers[] = "X-AVOSCloud-Application-Key:a43hb8wwvcioohjfd6t4i9xhgkc3sfnx00xdp2wd59yi43xu";
        $headers[] = "X-AVOSCloud-Master-Key: fsqwtzt5q1gmi2h7fjq2h58cj6nac1wp46uqn1dje03xrjr4";
        $headers[] = "Content-Type: application/json";
        $ret = Util::curl($url, json_encode($data), 1, $headers);
        if (!Util::isJson($ret)) {
            \Yii::error('send LeanCloud sms failed: ' . var_export($ret, true) . ' param:' . var_export(func_get_args(), true));
            return false;
        }
        $ret = json_decode($ret, true);
        if (empty($ret)) {
            return true;
        } else {
            if (isset($ret['code']) && $ret['code'] == 601) {
                //发送过于频繁
                return false;
            } else {
                \Yii::error('send LeanCloud sms failed: ' . var_export($ret, true) . ' param:' . var_export(func_get_args(), true));
            }
            return false;
        }
    }

    public static function yunTongXunSendSMS($mobile, $content, $template, $param = array())
    {
        if (!isset($param['code'])) {
            \Yii::error('send yuntongxun sms failed: code not exist');
            return false;
        } else {
            $param['code'] = (string) $param['code'];
        }

        $map = [
            'register'       => '26345',
            'password'       => '26346',
            'confirm_mobile' => '26347',
            'bind_mobile'    => '26348',
        ];

        if (!isset($map[$template])) {
            \Yii::error('send yuntongxun sms failed: template id not found, template is ' . $template);
            return false;
        }

        $accountSid = "8a48b5514e04a574014e05ed2e8f022d";
        $autoToken = '880dfae147e4436e9147c767cf51e42b';
        $appId = 'aaf98f894e8a784b014e8a9c310e0054';
        $time = date('YmdHis');

        $sigParameter = strtoupper(md5($accountSid . $autoToken . $time));
        $authorization = base64_encode($accountSid . ":" . $time);

        $url = "https://sandboxapp.cloopen.com:8883/2013-12-26/Accounts/$accountSid/SMS/TemplateSMS?sig=$sigParameter";
        $data = [
            'to'         => $mobile,
            'templateId' => $map[$template],
            'appId'      => $appId,
            'datas'      => [$param['code']],
        ];
        $content = json_encode($data);

        $headers = [];
        $headers[] = "Accept:application/json;";
        $headers[] = "Content-Type:application/json;charset=utf-8";
        $headers[] = "Authorization:$authorization;";

        $ret = Util::curl($url, $content, 1, $headers);
        if (!Util::isJson($ret)) {
            \Yii::error('send yuntongxun sms failed: ' . var_export($ret, true) . ' param:' . var_export(func_get_args(), true));
            return false;
        }
        $ret = json_decode($ret, true);
        if (isset($ret['statusCode']) && $ret['statusCode'] == '000000') {
            return true;
        } else {
            if (isset($ret['statusCode']) && $ret['statusCode'] == '160021') {
                //发送过于频繁
                return false;
            } else {
                \Yii::error('send yuntongxun sms failed: ' . var_export($ret, true) . ' param:' . var_export(func_get_args(), true));
            }
            return false;
        }
    }

    public static function manOverseaSendSMS($mobile, $content)
    {
        if (YII_ENV == 'test') {
            return true;
        }

        $content .= '【启维文化】';
        $content = urlencode($content);
        $sn = 'SDK-WSS-010-09412';
        $pwd = '85$a8-fd';
        $pwd = strtoupper(md5($sn.$pwd));
        $url = "http://sdk2.entinfo.cn:8060/gjWebService.asmx/mdSmsSend_g?sn=$sn&pwd=$pwd&mobile=$mobile&content=$content&ext=&stime=&rrid=";
        $response = file_get_contents($url);
        $ret = simplexml_load_string($response);

        if(isset($ret[0]) && !in_array($ret[0], ['-1', '-2', '-4', '-5', '-6', '-9', '-10', '-12', '-14', '-18', '-19', '-20', '-22'])) {
            $ret = true;
        }
        else {
            \Yii::error('send man-oversea sms failed: ' . var_export($ret, true) . ' param:' . var_export(func_get_args(), true));
            $ret = false;
        }

        DataLog::logOverseaSendSMSMark(0, '', [], $mobile, (int) $ret, $content);

        return $ret;
    }

    public static function dreamPromotionSendSMS($mobile, $content)
    {
        $msgContent = urlencode($content);
        $url = "http://61.145.229.29:8892/MWGate/wmgw.asmx/MongateSendSubmit?userId=JH0982&password=123195&pszMobis=$mobile&pszMsg=$msgContent&iMobiCount=1&pszSubPort=*";
        $response = file_get_contents($url);
        $ret = simplexml_load_string($response);

        if(isset($ret[0]) && !in_array($ret[0], self::$dreamErrorCodes)) {
            return true;
        }

        \Yii::error('send dream sms failed: ' . var_export($ret, true) . ' param:' . var_export(func_get_args(), true));

        return false;
    }

    protected static function getCodeCache()
    {
        return self::cache('echoCode');
    }
    
    public static function curl($url, $data = '', $method = 0, $headers = [], $userAgent = '', $connectTimeout=3, $timeout=3, $is_throw_exception = false, & $options = null) {
    	
    	$ch = curl_init($url);
    	curl_setopt ($ch, CURLOPT_HEADER, 0);
    	curl_setopt ($ch, CURLOPT_CONNECTTIMEOUT, $connectTimeout);
    	curl_setopt ($ch, CURLOPT_TIMEOUT, $timeout);
    	curl_setopt ($ch, CURLOPT_RETURNTRANSFER, 1);
    	curl_setopt ($ch, CURLOPT_USERAGENT, $userAgent);
    	curl_setopt ($ch, CURLOPT_REFERER, $url);
        curl_setopt ($ch, CURLOPT_HTTPHEADER , $headers);
        curl_setopt ($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt ($ch, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4);
        $httpProxy = Util::get($options, 'http_proxy');
        if($httpProxy) {
            curl_setopt($ch, CURLOPT_PROXY, $httpProxy);
        }

        if(is_string($method)) {
            $method = strtoupper($method);
        }
    	if ( $method == 1 || $method === 'POST') {
    		curl_setopt ($ch, CURLOPT_POST, true);
    		curl_setopt ($ch, CURLOPT_POSTFIELDS, is_array($data) ? http_build_query($data) : $data );
    	} else if ( $method == 2 || $method === 'PUT') {
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
    		curl_setopt ($ch, CURLOPT_POSTFIELDS, $data );
    	} else if(is_string($method)) {
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        }

        if(is_array($data) && isset($data['authentication'])) {
            curl_setopt($ch, CURLOPT_USERPWD, $data['authentication']);
        }

        $t = microtime(true);
    	$ret = curl_exec($ch);
        $d = microtime(true) - $t;

        if ($ret === false) {
            $error = curl_error($ch);
            $info =  curl_getinfo($ch);
            if($options) {
                $options['error'] = [
                    'no' => curl_errno($ch),
                    'message' => $error,
                ];
            }
            $error_msg = 'Util curl error: ' . var_export($error, true) . ' , time spent is ' . $d . 's, info is ' . var_export($info, true) . ' ,param is :' . var_export(func_get_args(), true);
            Yii::error($error_msg);
            if ($is_throw_exception) {
                throw new \Exception($error_msg);
            }
        }

        curl_close ($ch);
    	
    	return $ret;
    }

    public static function curlViaKeepAliveProxy($url, $data='', $method = 0, $headers = [], $userAgent = '', $connectTimeout=3, $timeout=3, $proxy = null /* use default proxy in params.php */) {
        if(/*isset($_GET['p']) || */YII_ENV !== 'dev') {
            $xProtocol = null;
            $httpUrl = $url;
            if(preg_match('#^https://#i', $url)) {
                $xProtocol = 'https';
                $httpUrl = 'http://' . substr($url, 8);
            }
            $proxies = $proxy === null ? Yii::$app->params['http_proxies'] : [$proxy, ];
            $options = [
                'http_proxy' => $proxies[ array_rand($proxies) ],
            ];

            $fixedHeaders = array_merge($headers ?: [], ["X-Protocol: $xProtocol", ]);
            $ret = static::curl($httpUrl, $data, $method, $fixedHeaders, $userAgent, $connectTimeout, $timeout, false, $options);
            if($ret !== false) {
                return $ret;
            }

            if(! in_array($options['error']['no'], [CURLE_COULDNT_CONNECT, ]) && stripos($options['error']['message'], 'Connection timed out') === false) { // otherwise if connection refused or timed out, we re-make request ourselves
                return false;
            }
        }

        return static::curl($url, $data, $method, $headers, $userAgent, $connectTimeout, $timeout);
    }

    public static function systemCall($handlers, $queueName, $enqueue = null, $sync = false) {
        $enqueue = static::shouldEnqueue($enqueue);

        if($enqueue) {
            QueueClient::enqueue(compact('handlers'), '', $queueName ?: 'system_call_router', true, $sync);
        } else {
            if(is_array($handlers)) {
                if(! is_array($handlers[0])) {
                    $handlers = [ $handlers ];
                }
                try {
                    foreach($handlers as $handler) {
                        if(is_array($handler) && is_callable($handler[0])) {
                            call_user_func_array($handler[0], array_slice($handler, 1));
                        }
                    }
                } catch(\Exception $e) {
                    Yii::error('systemCall ERROR, handlers are ' . json_encode($handlers, JSON_UNESCAPED_UNICODE) . ', exception is ' . $e);
                }
            }
        }
    }

    public static function shouldEnqueue($enqueue = null) {
        if($enqueue === null) {
            $enqueue = YII_ENV !== 'dev' || (strpos(static::get($_SERVER, 'HTTP_HOST'), 'lab') !== false);
        }
        return $enqueue;
    }

    public static function getBlockIps()
    {
        $blockIpsStr = SysSetting::get('echoapp_block_ip');
        if(!$blockIpsStr) {
            return null;
        }

        return explode(',', $blockIpsStr);
    }

    public static function getIp()
    {

        return static::getClientIp();
        
        if (getenv('HTTP_CLIENT_IP')) {
            $ip = getenv('HTTP_CLIENT_IP');
        }
        elseif (getenv('HTTP_X_FORWARDED_FOR')) {
            $ip = getenv('HTTP_X_FORWARDED_FOR');
        }
        elseif (getenv('HTTP_X_FORWARDED')) {
            $ip = getenv('HTTP_X_FORWARDED');
        }
        elseif (getenv('HTTP_FORWARDED_FOR')) {
            $ip = getenv('HTTP_FORWARDED_FOR');

        }
        elseif (getenv('HTTP_FORWARDED')) {
            $ip = getenv('HTTP_FORWARDED');
        }
        else {
            $ip = $_SERVER['REMOTE_ADDR'];
        }

        return $ip;
    }

    public static function getBlockPhones()
    {
        $blockIPhonesStr = SysSetting::get('echoapp_block_phone');
        if(!$blockIPhonesStr) {
            return null;
        }

        return explode(',', $blockIPhonesStr);
    }

    public static function isBlockIp()
    {
        $ip = self::getIp();
        $blockIps = self::getBlockIps();
        if(!$blockIps) {
            return false;
        }

        return in_array($ip, $blockIps);
    }

    public static function isBlockPhoneUser($userId)
    {
        $phone = EchoUser::getUserPhone($userId);
        if(!$phone) {
            return true;
        }

        $blockPhones = self::getBlockPhones();
        if(!$blockPhones) {
            return false;
        }

        return in_array($phone, $blockPhones);
    }

    public static function isBlockDevice()
    {
        try {
            $token = Util::agent()['device_token'];
            if (!$token) {
                return false;
            }

            /** @var RedisCache $cache */
            $cache = \yii::$app->redis->getCache('smallData');

            return $cache->sIsMember('disable_agent', $token);
        } catch (\Exception $e) {
            Yii::error('Check block device failed: ' . (string) $e);

            return false;
        }
    }

    public static function sortArrayByKey($list, $key, $isDesc = false)
    {
    	$tmpArray = [];
    	if ( ! is_array($list) ) {
    		return [];
    	}
    	
    	foreach ( $list as $k => $v ) {
    		$tmpArray[$k] = $v[$key];
    	}

    	$isDesc ? arsort($tmpArray) : asort($tmpArray);
    	
    	$result = [];
    	foreach ( $tmpArray as $k2 => $v2 ) {
    		$result[] = $list[$k2];
    	}
    	
    	return $result;
    }
    
	public static function encodeSystem($str, $index = self::CONVERT_62_INDEX) {
		$base = strlen($index);
		$ret = '';
		for($t = floor(log10($str) / log10($base)); $t >= 0; $t--) {
			$a = floor($str / pow($base, $t));
			$ret .= substr($index, $a, 1);
			$str -= $a * pow($base, $t);
		}
		return $ret;
	}

	public static function decodeSystem($str, $index = self::CONVERT_62_INDEX) {
		$base = strlen($index);
		$ret = 0;
		$len = strlen($str) - 1;
		for($t = 0; $t <= $len; $t++) {
			$ret += strpos($index, substr($str, $t, 1)) * pow($base, $len - $t);
		}
		return $ret;
	}

	public static function execCommand($controller, $action, $data = [], $logPrefix = 'app_', $logDir = 'echo_cron') {
		putenv("PATH=/usr/bin:/usr/local/bin:/usr/local/php/bin:" . getenv("PATH"));
		
		$sendData = [];
		if ( is_array($data) ) {
			foreach ( $data as $v ) {
				if ( ! strlen($v) ) {
					continue;
				}
				$sendData[] = preg_replace('/[^a-zA-Z0-9_-]+/', '', $v);
			}
		}
		
		$nohup = 'nohup ';
		if ( YII_ENV == 'prod' ) {
			$env = '';
		} else if ( YII_ENV == 'stage' ) {
			$env = '-stage';
		} else if ( YII_ENV == 'dev' ) {
			$env = '-dev';
			$nohup = '';
		}
		
		$params = implode(' ', $sendData);
		
		$path = Yii::$app->basePath;
		
		$command = "{$path}/yii{$env} {$controller}/{$action} {$params}";
		$logFile = "{$path}/runtime/logs/{$logPrefix}{$controller}_{$action}.log";
		$code = "{$nohup}{$command} >> {$logFile} 2>&1 &";
		exec($code);
	}

    //temp for rebuild event, will delete later
    public static function eventIdMap($activityId)
    {
//        $currentMapArr = [9 => 2, 14 => 3, 16 => 4, 17 => 5, 18 => 6];
//        if(array_key_exists($activityId, $currentMapArr)) {
//            $result = $currentMapArr[$activityId];
//        }
//        else {
//            $result = $activityId;
//        }

        return $activityId;
    }

    /**
     * return ["中国","浙江","温州",""]
     */
    public static function getIpInfoByIpIpNet($ip)
    {
        return IpIpNet::find($ip);
    }

    public static function toLower($str)
    {
    	$u = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
    	$l = 'abcdefghijklmnopqrstuvwxyz';
    	return strtr($str, $u, $l);
    }

    public static function toUpper($str)
    {
    	$u = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
    	$l = 'abcdefghijklmnopqrstuvwxyz';
    	return strtr($str, $l, $u);
    }

    /**
     * 把秒数转回为文本时间 4747277 => 54天22小时
     * @param     $timestamp
     * @param int $granularity 级别
     *
     * @return string
     */
    public static function secondToStringData($timestamp, $granularity = 2) {
        $units = array(
            array('i' => 31536000, 's' => \Yii::t('app', 'display unit year'), 'j' => 0),
            array('i' => 86400, 's' => \Yii::t('app', 'display unit day'), 'j' => 365),
            array('i' => 3600, 's' => \Yii::t('app', 'display unit hour'), 'j' => 24),
            array('i' => 60, 's' => \Yii::t('app', 'display unit minute'), 'j' => 60),
            array('i' => 1, 's' => \Yii::t('app', 'display unit second'), 'j' => 60),
        );

        foreach ($units as $key => $value) {
            if ($timestamp >= $value['i']) {
                $units[$key]['v'] = floor($timestamp / $value['i']);;
                $timestamp %= $value['i'];
            } else {
                $units[$key]['v'] = 0;
            }
        }

        $i = 0;
        $checkStartKey = count($units) - 1;
        foreach($units as $key => $value) {
            if ($value['v'] == 0) {
                continue;
            }

            $i++;
            if ($i > $granularity) {
                $checkStartKey = $key;
                break;
            }
        }

        if ($units[$checkStartKey]['v'] > ($units[$checkStartKey]['j'] / 2) && $checkStartKey !== 1) {
            $units[$checkStartKey-1]['v']++;
            $units[$checkStartKey]['v'] = 0;
        }

        for($i = $checkStartKey;$i >= 0;$i--) {
            $value = $units[$i];
            if (($value['v'] == $value['j']) && $i >= 1) {
                $units[$i-1]['v']++;
                $units[$i]['v'] = 0;
            }
        }

        $i = 0;
        $str = '';
        foreach($units as $key => &$value) {
            if ($value['v'] == 0) {
                continue;
            }

            $i++;
            $str .= $value['v'] . $value['s'];

            if ($i >= $granularity) {
                break;
            }
        }

        return $str;
    }

    /**
     * 把字符串映射到 数字
     * @param     $string
     * @param int $intLong
     *
     * @return int
     */
    public static function stringHashToInt($string, $intLong = 2)
    {
        return ((int) substr((string) base_convert(md5($string), 16, 10), 0, $intLong));
    }

    public static $chinaStateCode = array(
        '110000' => '北京市',
        '120000' => '天津市',
        '130000' => '河北省',
        '140000' => '山西省',
        '150000' => '内蒙古自治区',
        '210000' => '辽宁省',
        '220000' => '吉林省',
        '230000' => '黑龙江省',
        '310000' => '上海市',
        '320000' => '江苏省',
        '330000' => '浙江省',
        '340000' => '安徽省',
        '350000' => '福建省',
        '360000' => '江西省',
        '370000' => '山东省',
        '410000' => '河南省',
        '420000' => '湖北省',
        '430000' => '湖南省',
        '440000' => '广东省',
        '450000' => '广西壮族自治区',
        '460000' => '海南省',
        '500000' => '重庆市',
        '510000' => '四川省',
        '520000' => '贵州省',
        '530000' => '云南省',
        '540000' => '西藏自治区',
        '610000' => '陕西省',
        '620000' => '甘肃省',
        '630000' => '青海省',
        '640000' => '宁夏回族自治区',
        '650000' => '新疆维吾尔自治区',
        '710000' => '台湾省',
        '810000' => '香港特别行政区',
        '820000' => '澳门特别行政区',
    );

    /**
     * 获取省名字通过区域编码
     */
    public static function getStateNameByCode($code)
    {
        return isset(self::$chinaStateCode[$code]) ? self::$chinaStateCode[$code] : null;
    }

    public static function getStateCodeByName($name)
    {
        foreach(self::$chinaStateCode as $code => $stateName) {
            if (strpos($stateName, $name) !== false) {
                return $code;
            }
        }

        return null;
    }

    // check if $string is empty (we think 0 as non-empty string)
    public static function isEmpty($string, $trim = false)
    {
        if($trim) {
            $string = trim($string);
        }
        return ! ($string || $string === '0');
    }

    public static function getClientAppEnvNoImportant($key = null, $asInt = false, $default = null){

        $httpKey = 'HTTP_X_' . strtoupper($key);
        if(isset($_SERVER[$httpKey])) {
            return $asInt?(int)$_SERVER[$httpKey]:$_SERVER[$httpKey];
        }else{
            return $default;
        }

    }

    public static function getClientAppEnv($key = null, $asInt = false, $default = null) {
        static $app_env = null;
        if($app_env === null) {
            $app_env = [];
            foreach(self::$commonRequestHeaders as $field) {
                $httpKey = 'HTTP_X_' . strtoupper($field);
                if(isset($_SERVER[$httpKey])) {
                    $app_env[$field] = $_SERVER[$httpKey];
                }
            }
        }
        if($key === null) {
            return $app_env;
        }
        $value = self::get($app_env, $key, $default);
        return $asInt ? (int)$value : $value;
    }

    public static function getNet($default=null){
        $net = static::getClientAppEnv('net',false,$default);
        return $net===null ? $net : strtolower($net);
    }

    public static function getUuid($default = null) {
        return static::getClientAppEnv('uuid', false, $default);
    }

    public static function signEnabled() {
        static $sign_enabled = null;
        if($sign_enabled === null) {
            $appEnv = self::getClientAppEnv();
            $sign_enabled = isset($appEnv['c']) && isset($appEnv['v']);
        }
        return $sign_enabled;
    }

    public static function getRightAppSign($timestamp = null, $withSecret = true) {
        if($timestamp === null) {
            $sn = static::getClientAppEnv('sn');
            $timestamp = static::get(explode('/', $sn, 2), 1);
        }
        $version = static::getClientAppEnv('v', true, 0);
        $secret = ($version >= 102 && $version <= 107) ? 'b12588715a8746b44247ad083864c434' : 'b2dd23c8b818c73ba2ae519227716a7f';
        return sha1(($withSecret ? $secret : '') . $timestamp);
    }

    public static function getAppSignFromRequest() {
        return static::get($_SERVER, 'HTTP_X_A_SN');
    }

    public static function validateAppSign($appSign = null) {
        if($appSign === null) {
            $appSign = static::getAppSignFromRequest();
        }

        return $appSign === static::getRightAppSign();
    }

    public static function getGDTApi($deviceType = null, $uuid = null)
    {
        $deviceType = $deviceType ? : self::getDeviceType();
        $uuid = $uuid ? : self::getClientAppEnv('uuid');

        $muid = $uuid ? urlencode(md5($uuid)) : null;
        $convTime = urlencode(time());
//        $clientIp = urlencode(self::getIp());

        if(!$muid) {
            return null;
        }

//        $queryString = 'muid='.$muid.'&conv_time='.$convTime.'&client_ip='.$clientIp;
        $queryString = 'muid='.$muid.'&conv_time='.$convTime;
        $page = EchoGlobalConstant::GDT_API_URL.EchoGlobalConstant::GDT_APP_ID.'/conv?'.$queryString;
        $encodePage = urlencode($page);
        $property = EchoGlobalConstant::GDT_SIGN_KEY.'&GET&'.$encodePage;
        $signature = md5($property);
        $baseData = $queryString.'&sign='.urlencode($signature);
        $data = base64_encode(self::simpleXor($baseData, EchoGlobalConstant::GDT_ENCRYPT_KEY));

        $appType = urlencode(strtoupper($deviceType));
        $attachment = 'conv_type='.urlencode(EchoGlobalConstant::GDT_CONV_TYPE).'&app_type='.$appType.'&advertiser_id='.urlencode(EchoGlobalConstant::GDT_UID);

        return EchoGlobalConstant::GDT_API_URL.EchoGlobalConstant::GDT_APP_ID.'/conv?v='.$data.'&'.$attachment;
    }

    public static function simpleXor($baseData, $encryptKey)
    {
        $dataLen = strlen($baseData);
        $keyLen = strlen($encryptKey);

        $result = '';
        $j = 0;
        for($i = 0; $i < $dataLen; $i++) {
            $result .= chr(ord($baseData[$i]) ^ ord($encryptKey[$j]) );
            $j = ($j + 1) % $keyLen;
        }

        return $result;
    }

    public static function fieldEncryption($ret)
    {
        if (!is_array($ret) || self::getSource() != 2) {
            return $ret;
        }

        foreach ($ret as $key => $value) {
            if (is_array($value)) {
                $value = self::fieldEncryption($value);
            }

            if (isset(EchoGlobalConstant::$fieldEncryptionMap[$key])) {
                $ret[EchoGlobalConstant::$fieldEncryptionMap[$key]] = $value;

                if (self::isNewVersion(null, 80)) {
                    unset($ret[$key]);
                }
            }
        }

        return $ret;
    }

    public static function enqueue($body, $routingKey = 'push.message', $exchangeName = 'system_exchange', $encodeMsg = true)
    {
        call_user_func_array(array('app\library\queue\QueueClient', 'enqueue'), func_get_args());
    }

    /**
     * 注意安卓的app id实际上有可能是类似com.kibey.echo.global.googleplay这种,所以在获取安卓的app id的时候要兼容处理!!
     * 
     * 判断是否是国际版请使用方法Util::isEchoGlobal()!!!!!!!
     */
    public static function getAppId()
    {
        static $bundleId = null;
        if ($bundleId !== null) {
            return $bundleId;
        }

        $src = self::getSource();
        if ($src == 2) {
            //android
            if (self::isEchoGlobal()) {
                $bundleId = 'com.kibey.echo.global';
            } else {
                $bundleId = 'com.kibey.echo';
            }
        } else if ($src == 3) {
            $appEnv = self::getClientAppEnv();
            $appTypeId = isset($appEnv['at']) ? $appEnv['at'] : 1;
            //web hybrid 强制把自己当成echoplus need FIXME
            if (YII_FRONTEND == 'web') {
                $appTypeId = 2;
            }
            if (self::isEchoGlobal()) {
                $appTypeId = 3;
            }
            if (isset(EchoGlobalConstant::$iosBundleIdArray[$appTypeId])) {
                $bundleId = EchoGlobalConstant::$iosBundleIdArray[$appTypeId];
            } else {
                $bundleId = EchoGlobalConstant::$iosBundleIdArray[1];
                Yii::error('Get app type error user the default app id');
            }
        } else {
            $bundleId = false;
        }

        return $bundleId;
    }

    /**
     * apple plus
     */
    public static function isEchoPlus()
    {
        if (self::getAppId() === 'com.kibey.echoplus') {
            return true;
        } else {
            return false;
        }
    }

    /**
     * android plus
     */

    public static function isAndroidEchoPlus() {
        $appEnv = self::getClientAppEnv();
        $appTypeId = isset($appEnv['at']) ? $appEnv['at'] : 1;
        if($appTypeId == 3) {
            return true;
        }
        return false;
    }

    public static function isAndroidNormal() {
        $appEnv = self::getClientAppEnv();
        $appTypeId = isset($appEnv['at']) ? $appEnv['at'] : 1;
        $c = self::getSource();
        if($c == 2 && $appTypeId != 3) {
            return true;
        }
        return false;
    }

    public static function isEchoGlobal()
    {
        if ( ! isset(Yii::$app->request->headers) ) {
            return false;
        }
        $headers = Yii::$app->request->headers;
        $bundleId = $headers->get('x-bundle-id');
        if ($bundleId === EchoLanguage::IOS_XBUILD_GLOBAL || $bundleId === EchoLanguage::XBUILD_GLOBAL || StringHelper::startsWith($bundleId, EchoLanguage::XBUILD_GLOBAL)) {
            return true;
        } else {
            return false;
        }
    }

    public static function getIpCountry()
    {
        if(!self::isEchoGlobal())
        {
            return EchoLanguage::SOUND_COPYRIGHT_COMMON;
        }

        return isset(Yii::$app->params['ip-country']) ? Yii::$app->params['ip-country'] : EchoLanguage::SOUND_COPYRIGHT_COMMON;

    }

    public static function processUrlInContent(&$content, &$urlInfo)
    {
        $content = str_replace('/', '//', $content);
        $preg = '#(?:http|https)\:\/\/\/\/(?:[-a-z0-9@:%_\+~\#=]++\.)+[0-9a-z_-]++(?::\d+)?(?:(?:\/\/|[\?\#])[\x21-\x7e]*+)?#i';
        preg_match_all($preg, $content, $out);
        if($out[0]) {
            foreach($out[0] as $k => $v) {
                $urlInfo[$k]['url'] = str_replace('//', '/', $v);
                $urlInfo[$k]['text'] = EchoGlobalConstant::URL_INFO_TEXT;
            }
            $content = preg_replace($preg, '/#', $content);
        }
    }

    // 获取用户代理类型，可判断网页是在echo/lucky app中打开的情况，如果确定只在echo中使用，可使用\app\library\Util::getSource('string')获取
    public static function getViewWay($checkAppUa = true) {
        $type = 'other';
        $agent = isset($_SERVER['HTTP_USER_AGENT']) ? strtolower($_SERVER['HTTP_USER_AGENT']) : '';

        if(preg_match('/iphone|ios|ipod|ipad/', $agent)) {
            $type = 'ios';
            // echo ios 1.2 20151023;(iPhone,iPhone OS8.3);IDFA E548B1B8-670F-AB8D-850E-2A42D0804665
            if($checkAppUa && preg_match('/idfa [a-f0-9-]{32,}+/', $agent)) {
                $type = 'ios_app';
            }
        }

        if(preg_match('/android/', $agent)) {
            $type = 'android';
            if($checkAppUa && preg_match('/[a-f0-9-]{32,}+/', $agent)) {
                $type = 'android_app';
            }
        }

        return $type;
    }

    public static function luckyRpc($userId, $relativeUrl, $data = [], $headers = [], $connectTimeout = 10, $timeout = 10) {
        $passwordHash = EchoRpcAuthUser::retrievePasswordHashByUserId($userId);
        $r = Util::curl(\Yii::$app->params['lucky_api_base_url'] . $relativeUrl, [
            'user_id' => $userId,
            'original_password_hash' => $passwordHash,
        ] + $data, 1, $headers, 'echo php-no-agent curl', $connectTimeout, $timeout);
        $r = $r ? json_decode($r, true) : $r;
        if(static::get($r, 'state') === 0) { // ok
            return $r['result'];
        }
        \Yii::error(new \Exception('Util::luckyRpc error: r=' . json_encode($r, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)));
        return false;
    }

    public static function isClientDebug()
    {
        $appEnv = self::getClientAppEnv();
        if (isset($appEnv['cd']) && $appEnv['cd'] == 1) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * @param $avatar 图片地址
     * @return array|bool|null|string
     */
    public static function fetchAvatar($avatar) {
        if(! $avatar || static::isQiniuUrl($avatar)) {
            return null;
        }
        /**
         * @var \app\library\qiniu\QiniuClient $qiniu
         */
        $qiniu = Yii::$app->qiniu;
        $bucket = 'echo-image';
        $key = 'wb/' . sha1($avatar);
        $r = $qiniu->fetchThenUpload($avatar, $bucket, $key);
        if($r && !isset($r['error'])) {
            return $qiniu->getUrlByKeyAndBucket($key, $bucket);
        }
        return false;
    }

    public static function isCN()
    {
        $cnLanguage = ['zh-CN','zh-TW'];
        $arr = ['app\controllers\api', 'app\controllers\frontend'];
        if ( in_array(\Yii::$app->language,$cnLanguage) || ( ! in_array(\Yii::$app->controllerNamespace, $arr))){
            return true;
        }
            
        return false;
    }

    /**
     * 仅判断是否是大陆地区 【区别Util::isCN  不包含港澳台】
     */
    public static function isHans()
    {
        $arr = ['app\controllers\api', 'app\controllers\frontend'];
        if (\Yii::$app->language == "zh-CN" || ( ! in_array(\Yii::$app->controllerNamespace, $arr))){
            return true;
        }
        return false;
    }

    /**
     * 仅判断是否是港澳台
     */
    public static function isHant()
    {
        return \Yii::$app->language == "zh-TW" ;
    }
    
    public static function deleteArrayEle($arr, $key = null, $value = null){
    	$result = [];
    	foreach ( $arr as $k => $v ) {
    		if ( $key !== null && $key == $k ) {
    			continue;
    		}
    		if ( $value !== null && $value == $v ) {
    			continue;
    		}
    		$result[$k] = $v;
    	}
    	return $result;
    }

    public static function getCurrentLan($checkGlobal  = false)
    {
        if(!self::isEchoGlobal() && $checkGlobal)
        {
            return 'zh-CN';
        }
        return isset(\Yii::$app->language) ? \Yii::$app->language : 'zh-CN';
    }

    public static function Out($state = 1, $message = 'success', $result = Array())
    {
        return array(
            'state' => $state,
            'message' => $message,
            'result' => $result
        );
    }

    /**
     * 字符串截取
     * @from mardin
     * @param $text
     * @param int $max
     * @param string $overflowText
     * @param string $encoding
     * @return string
     */
    public static function str_cut($text, $max = 32, $overflowText = '...', $encoding = 'utf-8') {
        $len = mb_strlen($text, $encoding);
        $ret = mb_substr($text, 0, $max, $encoding);
        if ($len > $max) {
            $ret .= $overflowText;
        }
        return $ret;
    }

    /**
     * 获取用户所在地区语言
     * 
     * @param int $user_id 用户id
     * @param string $source 用户来源 可选值:'ios' 或 'android'
     * 
     * @return string 语言代码:'en-US', 'zh-CN', 'ja-JP', 'ko-KR'
     */
    public static function getUserDeviceLanguage($user_id = null, $source = 'ios')
    {
        if (empty($user_id) && !(\Yii::$app instanceof \yii\console\Application)) {
            $user_id = \Yii::$app->user->id;
        }

        if (empty($user_id))
            return false;

        $cache = self::cache('echoTranslate');
        $lan = $cache->get("app_language_user:" . $user_id . ":source:" . $source);
        $lan = $lan ? $lan : 'zh-CN';//给个默认值
        return $lan;
    }
    
    public static function arrayFilter($array, $filter)
    {
        if(!$array || !$filter || !is_array($array) || !is_array($filter)) {
            return [];
        }
        
        $result = [];
        foreach($filter as $key) {
            $result[$key] = self::get($array, $key);
        }
        
        return $result;
    }

    public static function getQrCodeUrl($qrStr, $bucket = 'qr_code') {
        $url = "http://s.jiathis.com/qrcode.php?url={$qrStr}";

        $qiniuUrl = "ticket/{$bucket}" . md5($bucket.$qrStr.time());

        $ret = \Yii::$app->qiniu->fetchThenUpload($url, 'echo-image', $qiniuUrl);

        if ($ret) {
            $qrUrl = 'http://' . \Yii::$app->qiniu->getDomainByBucket('echo-image') . '/' . $qiniuUrl;
        } else {
            $qrUrl = $url;
        }

        return $qrUrl;
    }

    public static function getXCountry()
    {
        if (isset(Yii::$app->params['x-country'])) {
            return Yii::$app->params['x-country'];
        } else {
            return EchoLanguage::XCOUNTRY_CN;
        }
    }

    public static function calculateMemoryOccupiedForVariable($var, $keyLength = -1) {
        static $typeUsage = null;
        static $info = null;
        static $intSize = PHP_INT_SIZE;
        $type = gettype($var);
        if($keyLength < 0) {
            $info = null;
            $keyLength = 0;
        }
        if($info === null) {
            $info = [
                'type' => $type,
                'num_elements_of_top_level' => 0,
                'depth' => 0,
                'max_num_elements' => 0,
                'depth_corresponding_to_max_num_elements' => 0,
                'total_bytes_occupied_estimated' => 0,
                'total_bytes_key_occupied_estimated' => 0,
                'total_num_elements' => 0,
                'total_num_leaf_elements' => 0,
            ];
        }
        if($typeUsage === null) {
            $typeUsage = [
                'boolean' => 1,
                'integer' => $intSize,
                'double' => 8,
                'resource' => $intSize,
                'NULL' => 1,
            ];
        }
        ++$info['total_num_elements'];
        if($type === 'array' || $type === 'object') {
            $depth = ++$info['depth'];
            if($type === 'array') {
                $numElements = count($var);
                if($numElements > $info['max_num_elements']) {
                    $info['max_num_elements'] = $numElements;
                    $info['depth_corresponding_to_max_num_elements'] = $info['depth'];
                }
                foreach($var as $key => $value) {
                    static::calculateMemoryOccupiedForVariable($value, is_string($key) ? strlen($key) : $intSize);
                }
            } else {
                $numElements = 0;
                foreach($var as $key => $value) {
                    ++$numElements;
                    static::calculateMemoryOccupiedForVariable($value, is_string($key) ? strlen($key) : $intSize);
                }
                if($numElements > $info['max_num_elements']) {
                    $info['max_num_elements'] = $numElements;
                    $info['depth_corresponding_to_max_num_elements'] = $info['depth'];
                }
            }
            if($depth === 1) {
                $info['num_elements_of_top_level'] = $numElements;
            }
            $info['total_bytes_key_occupied_estimated'] += $keyLength;
            $info['total_bytes_occupied_estimated'] += $keyLength;
            return $info;
        }
        $info['total_bytes_key_occupied_estimated'] += $keyLength;
        $varBytesOccupied = isset($typeUsage[$type]) ? $typeUsage[$type] : ($type === 'string' ? strlen($var) : 0);
        $info['total_bytes_occupied_estimated'] += $keyLength + $varBytesOccupied;
        ++$info['total_num_leaf_elements'];
        return $info;
    }

    public static function redisCmd($cacheName, $method, $args /* ... */) {
        $cache = static::cache($cacheName);
        return call_user_func_array([$cache, $method], array_slice(func_get_args(), 2));
    }

    public static function isStressTest() {
        static $isStressTest = null;
        if($isStressTest === null) {
            $isStressTest = defined('STRESS_TEST_VERSION');
        }
        return $isStressTest;
    }

    public static function isGlobalApp() {
        if (isset(Yii::$app->params['is_global_app']) && Yii::$app->params['is_global_app']) {
            return true;
        }
        return false;
    }

    public static function getArea($area = null)
    {
        if (!$area) {
            $xCountry = (int)self::getXCountry();
            $area = EchoAutoCommend::AREA_CHINA;
            if ($xCountry != EchoLanguage::XCOUNTRY_CN) {
                $area = EchoAutoCommend::AREA_OTHER;
            }
        }
        return $area;
    }

    public static function fixUserAvatars($step = 3000, $execute = false, $minId = null, $maxId = null) {
        $imageExists = function ($url) {
            return ! $url || $url[0] === '/' || self::isQiniuUrl($url) || stripos($url, 'upaiyun') !== false;
        };
        $qiniu = QiniuClient::instance();
        EchoUser::walkAll(function(EchoUser $record, $index, $env) use ($imageExists, $qiniu, $execute) {
            $avatarOk = $imageExists($record->avatar);
            $photoOk = $imageExists($record->photo);
            if($avatarOk && $photoOk) {
                return;
            }
            $dstBucket = 'echo-image';
            $userInfo = [];
            $qiniuInfo = [];
            if(! $avatarOk) {
                $field = 'avatar';
                $url = $record->$field;
                $dstKey = $field . '/' . sha1($record->$field);
                $result = $qiniu->fetchThenUpload($url, $dstBucket, $dstKey);
                $dstUrl = $qiniu->getUrlByKeyAndBucket($dstKey, $dstBucket);
                $qiniuInfo[$field] = ['result' => $result];
                if($result && preg_match('#^image/#i', (string)Util::get($result, 'mimeType'))) {
                    $userInfo = array_merge($userInfo, ['id' => $record->id, $field => $dstUrl]);
                    $qiniuInfo[$field][$field . '_ok'] = 1;
                }
            }

            if(! $photoOk) {
                $field = 'photo';
                $url = $record->$field;
                $dstKey = $field . '/' . sha1($record->$field);
                $result = $qiniu->fetchThenUpload($url, $dstBucket, $dstKey);
                $dstUrl = $qiniu->getUrlByKeyAndBucket($dstKey, $dstBucket);
                $qiniuInfo[$field] = ['result' => $result];
                if($result && preg_match('#^image/#i', (string)Util::get($result, 'mimeType'))) {
                    $userInfo = array_merge($userInfo, ['id' => $record->id, $field => $dstUrl]);
                    $qiniuInfo[$field][$field . '_ok'] = 1;
                }
            }

            $s = [];
            $act = $execute ? 'EXEC' : 'DRY_EXEC';
            $latestUser = '_';
            if($userInfo && $execute) {
                $latestUser = EchoUser::find()->select(['avatar', 'photo'])->where(['id' => $record->id])->asArray()->one();
                if($latestUser && $latestUser['avatar'] === $record->avatar && $latestUser['photo'] = $record->photo) {
                    EchoUser::updateUserInfo($userInfo);
                } else {
                    $act = 'NO_EXEC_BECAUSE_DATA_CHANGED';
                }
            }
            $s[] = $act;
            $s[] = $record->id;
            $s[] = json_encode($record->avatar, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            $s[] = json_encode($record->photo, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            $s[] = json_encode($userInfo, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            $s[] = json_encode($qiniuInfo, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            $s[] = json_encode($latestUser, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

            echo implode("\t", $s) . "\n";
            flush();

//            echo "\n";
//            echo json_encode(['i' => $index, 'r' => $record->toArray(), 'env' => $env]);
//            echo "\n\n";
//            flush();
        }, $step, ['id', 'avatar', 'photo', ], $minId, $maxId);
    }
    
    /**
     * 判断语言地区是否是通用版语言地区
     * 
     * @param int $xCountry
     * @return bool
     */
    public static function isUniversalCountry($xCountry)
    {
        if ($xCountry !== null) {
            return in_array($xCountry, EchoLanguage::$universalCountryArr);
        } else {
            return in_array(self::getXCountry(), EchoLanguage::$universalCountryArr);
        }
    }

    /**
     * @param $header 一维数组 ['声音ID','收听数','喜欢数','评论数','分享数','推荐时间']
     * @param $data     二维数组
     * @param $title  标题
     * @param $fileName 文件名
     * @param bool $setWidth 一维数组 ['A'=>10,'B'=>18,'C'=>10]
     * @param bool $center 居中 'A1:G300'
     */
    public static function exportExcel($header, $data, $title, $fileName, $setWidth=false, $center=false)
    {

        $objPHPExcel = new \PHPExcel();

        // Set document properties
        $objPHPExcel->getProperties()->setCreator("Maarten Balliauw")
            ->setLastModifiedBy("Maarten Balliauw")
            ->setTitle("PHPExcel Test Document")
            ->setSubject("PHPExcel Test Document")
            ->setDescription("Test document for PHPExcel, generated using PHP classes.")
            ->setKeywords("office PHPExcel php")
            ->setCategory("Test result file");


        $num = 0;
        foreach($header as $v){
            $colum = \PHPExcel_Cell::stringFromColumnIndex($num);
            $objPHPExcel->setActiveSheetIndex(0)->setCellValue($colum.'1', $v);
            $num += 1;
        }

        $column = 2;
        foreach($data as $rows){
            $span = 0;
            foreach($rows as $value){
                $j = \PHPExcel_Cell::stringFromColumnIndex($span);
                $objPHPExcel->getActiveSheet()->setCellValue($j.$column, $value);
                $span++;
            }
            $column++;
        }

        //设置宽度
        if($setWidth){
            foreach($setWidth as $k=>$v){
                $objPHPExcel->getActiveSheet()->getColumnDimension($k)->setWidth($v);
            }
        }

        //设置居中
        if($center){
            $objPHPExcel->getActiveSheet()->getStyle($center)
                ->getAlignment()->setHorizontal(\PHPExcel_Style_Alignment::HORIZONTAL_CENTER);
        }

        // Rename worksheet
        $objPHPExcel->getActiveSheet()->setTitle($title);

        // Set active sheet index to the first sheet, so Excel opens this as the first sheet
        $objPHPExcel->setActiveSheetIndex(0);


        // Save Excel 2007 file
//        $month = date('m');
//        $objWriter = \PHPExcel_IOFactory::createWriter($objPHPExcel, 'Excel2007');
//        $objWriter->save(Yii::$app->basePath.'/upload/请假纪录-'.$month.'.xlsx');


        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment;filename='.$fileName);
        header('Cache-Control: max-age=0');
        $objWriter = \PHPExcel_IOFactory::createWriter($objPHPExcel, 'Excel2007');
        $objWriter->save('php://output');

    }

    public static function formatSoundViewCount($num = null, $isOriginal = 0)
    {
        if(!isset($num)) {
            return 0;
        }

        $num = (int) $num;

        if ($num < 1000) {
            return $num;
        }
        elseif ($num >= 1000 && $num < 10000) {
            return 10000;
        }
        elseif ($num >= 10000 && $num < 100000) {
            return 100000;
        }
        elseif ($num >= 100000 && $num < 1000000) {
            return 1000000;
        }
        elseif ($num >= 1000000 && $num < 10000000) {
            return 10000000;
        }
        else {
            return 20000000;
        }
    }

    # 是否大于5.8.1
    public static function isBigger581()
    {
        return self::isNewVersion(2016120100,136);
    }

    # 是否大于6.0
    public static function isBigger600()
    {
        if(YII_ENV == 'stage') {
            return true;
        }
        return self::isNewVersion(2017020804,154);
    }

    public static function curlQqMusicSingerInfo($markId)
    {
        $url = "https://c.y.qq.com/splcloud/fcgi-bin/fcg_get_singer_desc.fcg?singermid=$markId&utf8=1&outCharset=utf-8&format=xml";
        $headers = ["Referer: https://c.y.qq.com/xhr_proxy_utf8.html"];

        $output = self::curl($url, '', 0, $headers);

        if ($output === false) {
            return [];
        }

        $output = self::xmlToArray($output);
        $outputBasicArr = Util::arrayPath($output, 'result.data.info.basic.item');
        $outputDescStr = Util::arrayPath($output, 'result.data.info.desc');
        $descStrArr = explode('。', $outputDescStr);
        $descStr = '简介:'.$descStrArr[0].'。';
        $ret = '';

        if (is_array($outputBasicArr)) {
            foreach($outputBasicArr as $value) {
                $ret .= $value['key'].':'.$value['value'].", ";
            }
        }
        $ret .= $descStr;

        return $ret;
    }

    public static function getHeader(){
        
        $headers = [];
        array_filter(array_keys($_SERVER), function ($key) use(& $headers) {
            if(strpos($key, 'HTTP_') === 0) {
                $headers[ strtolower(substr(str_replace('_', '-', $key), 5)) ] = $_SERVER[$key];
            }
        });

        return $headers;
        
    }

    public static function curlQqMusicSingerAlbum($markId)
    {
        $url = "https://c.y.qq.com/v8/fcg-bin/fcg_v8_singer_album.fcg?jsonpCallback=MusicJsonCallbacksinger_album&hostUin=0&format=jsonp&inCharset=utf8&outCharset=utf-8&singermid=$markId&order=time&begin=0&num=30";
        $output = Util::curl($url);

        if ($output === false) {
            return [];
        }

        $output = preg_replace('/^[^{]+|[^}]+$/', '', $output);
        $outputArr = json_decode($output, true);
        $ret = [];
        if (isset($outputArr['data']['list']) && is_array($outputArr['data']['list'])) {
            foreach($outputArr['data']['list'] as $k => $value) {
                $ret[$k]['album_id'] = $value['albumID'];
                $ret[$k]['album_mark_id'] = $value['albumMID'];
                $ret[$k]['album_name'] = $value['albumName'];
                $ret[$k]['album_cover_url'] = 'https://y.gtimg.cn/music/photo_new/T002R300x300M000'.$value['albumMID'].'.jpg';
                $ret[$k]['album_publish_date'] = $value['pubTime'];
            }
        }

        return array_values($ret);
    }

    public static function curlQqMusicAlbumSongs($id)
    {
        $url = "https://c.y.qq.com/v8/fcg-bin/musicmall.fcg?cmd=get_album_buy_page&albumid=$id&jsonpCallback=MusicJsonCallback_digital&hostUin=0&format=jsonp&inCharset=utf8&outCharset=utf-8";
        $output = Util::curl($url);

        if ($output === false) {
            return [];
        }

        $output = preg_replace('/^[^{]+|[^}]+$/', '', $output);
        $outputArr = json_decode($output, true);
        $ret = [];
        foreach($outputArr['data']['songlist'] as $k => $value) {
            $ret[$k]['song_id'] = $value['songid'];
            $ret[$k]['song_mark_id'] = $value['songmid'];
            $ret[$k]['song_name'] = $value['songname'];
            $ret[$k]['song_url'] = 'https://y.qq.com/portal/song/'.$value['songmid'].'.html';
        }

        return array_values($ret);
    }

    public static function curlMusicUrl($url)
    {
        if(strpos($url, EchoGlobalConstant::QQ_MUSIC_HOST_KEY) === false
            && strpos($url, EchoGlobalConstant::XIAMI_MUSIC_HOST_KEY) === false
            && strpos($url, EchoGlobalConstant::NETEASE_MUSIC_HOST_KEY) === false
        ) {
            return [];
        }

        $regTitle = EchoGlobalConstant::TITLE_REG;
        if(strpos($url, EchoGlobalConstant::QQ_MUSIC_HOST_KEY) !== false) {
            $tag = EchoGlobalConstant::QQ_MUSIC_TAG;
            $regCover = EchoGlobalConstant::QQ_MUSIC_COVER_REG;
            $firstSplit = EchoGlobalConstant::QQ_MUSIC_FIRST_TITLE_SPLIT;
            $secondSplit = EchoGlobalConstant::QQ_MUSIC_SECOND_TITLE_SPLIT;
        }
        elseif(strpos($url, EchoGlobalConstant::XIAMI_MUSIC_HOST_KEY) !== false) {
            $tag = EchoGlobalConstant::XIAMI_MUSIC_TAG;
            $regCover = EchoGlobalConstant::XIAMI_MUSIC_COVER_REG;
            $firstSplit = EchoGlobalConstant::XIAMI_MUSIC_FIRST_TITLE_SPLIT;
            $secondSplit = EchoGlobalConstant::XIAMI_MUSIC_SECOND_TITLE_SPLIT;
        }
        else {
            $url = preg_replace('/#\//', '', $url);
            $tag = EchoGlobalConstant::NETEASE_MUSIC_TAG;
            $regCover = EchoGlobalConstant::NETEASE_MUSIC_COVER_REG;
            $firstSplit = EchoGlobalConstant::NETEASE_MUSIC_FIRST_TITLE_SPLIT;
            $secondSplit = null;
        }

        $ch = curl_init($url);

        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT_MS, 0);

        $output = curl_exec($ch);

        if(curl_exec($ch) === false || curl_errno($ch)){
            return [];
        }

        curl_close($ch);

        $reg = "!$regTitle|$regCover!u";
        $arr = [];
        preg_match_all($reg, $output, $arr);

        $titleArr = explode($firstSplit, $arr[1][0]);
        $secondSplit ? $titleArr = explode($secondSplit, $titleArr[0]) : null;

        $name = $titleArr[0];
        $singer = $titleArr[1];
        $cover = $arr[2][1];
        $cover = $tag == EchoGlobalConstant::QQ_MUSIC_TAG ? 'http:'.$cover : $cover;

//        if($tag == EchoGlobalConstant::QQ_MUSIC_TAG || $tag == EchoGlobalConstant::NETEASE_MUSIC_TAG) {
//            $cover = explode('?', $arr[2][1]);
//            $cover = $tag == EchoGlobalConstant::QQ_MUSIC_TAG ? 'http:'.$cover[0] : $cover[0];
//        }

        return compact('tag', 'name', 'singer', 'cover');
    }


    // convert xml to array, $node->nodeName as key, $node->nodeValue as value, note:
    // 1. attributes are ignored: <a id="1">xxxx</a> <=> <a>xxxx</a>
    // 2. leaf nodes(without child nodes) who have composite(with child nodes) sibling(s) are ignored: <a>xxx<b>b</b>yyy</a> <=> <a><b>b</b></a>
    // 3. multiple(more than 1) nodes with the same level & name are aggregated as a zero based array: <a><b>1</b><b><c>cc</c></b></a> => ['a' => 'b' => [1, 'c' => 'cc'] ]
    // 4. the contents of multiple(more than 1) sibling leaf nodes are concatenated: <d>xx <a> text1 <![CDATA[ cdata content ]]> text2 </a> xx</d> <=> <d><a> text1  cdata content  text2 </a></d> => ['d' => ['a' => ' text1  cdata content  text2 ']]
    public static function xmlToArray($xmlStringOrDomNode) {
        if(is_string($xmlStringOrDomNode)) {
            $doc = new \DOMDocument(); // ('1.0', 'utf-8');
            $doc->loadXML($xmlStringOrDomNode);
            $documentElement = $doc->documentElement;
            return [$documentElement->nodeName => self::xmlToArray($doc->documentElement)];
        }
        /**
         * @var $domNode \DOMNode
         */
        $domNode = $xmlStringOrDomNode;
        if ($domNode->hasChildNodes()) {
            $outArray = [];
            $childNodes = $domNode->childNodes;

            $singleCompositeNodeKeys = [];
            $singlePrimitiveNodeKeys = [];
            $primitiveNodeKeys = [];
            $hasCompositeChildNode = false;
            foreach($childNodes as $node) {
                /**
                 * @var \DOMNode $node
                 */
                $nodeName = $node->nodeName;
                $nodeType = $node->nodeType;
                if($nodeType === XML_COMMENT_NODE) {
                    continue;
                } else if(! in_array($nodeType, [XML_TEXT_NODE, XML_CDATA_SECTION_NODE])) {
                    $hasCompositeChildNode = true;
                    if(isset($outArray[$nodeName])) {
                        unset($singleCompositeNodeKeys[$nodeName]);
                    } else {
                        $singleCompositeNodeKeys[$nodeName] = 1;
                        $outArray[$nodeName] = [];
                    }
                    $outArray[$nodeName][] = self::xmlToArray($node);
                } else if(! $hasCompositeChildNode) {
                    $nodeName = '#text'; // overwrite node name
                    $primitiveNodeKeys[$nodeName] = 1;
                    if(isset($outArray[$nodeName])) {
                        unset($singlePrimitiveNodeKeys[$nodeName]);
                    } else {
                        $singlePrimitiveNodeKeys[$nodeName] = 1;
                        $outArray[$nodeName] = [];
                    }
                    $outArray[$nodeName][] = $node->nodeValue;
                }
            }
            foreach(array_merge($singleCompositeNodeKeys, $singlePrimitiveNodeKeys) as $nodeName => $value) {
                $outArray[$nodeName] = $outArray[$nodeName][0];
            }
            if($hasCompositeChildNode) {
                foreach($primitiveNodeKeys as $nodeName => $ignoredValue) {
                    unset($outArray[$nodeName]);
                }
            } else {
                $value = '';
                foreach($primitiveNodeKeys as $nodeName => $ignoredValue) {
                    $value .= is_array($outArray[$nodeName]) ? implode('', $outArray[$nodeName]) : $outArray[$nodeName];
                }
                return $value;
            }
            return $outArray;
        }

        return $domNode->nodeValue;
    }

    /**
     * 对数组进行Base64编码
     */
    public static function base64EcondeForArr($data){
        if(!is_array($data)){
            return null;
        }

        return base64_encode(json_encode($data));
    }

    /**
     * 解码 数组base64编码结果
     */
    public static function base64DecodeForArr($str){
        return json_decode(base64_decode($str),true);
    }

    public static function formatMoney($money)
    {
        if ($money >= 10000) {
            return sprintf("%.2f", $money / 10000) . '万';
        } else {
            return $money;
        }
        return $money;
    }
    /**
     * 合并增量入队列
     */
    public static function mergeIncreaseEnqueue($interval, $handle, $data, $value, $ignore = [], $queue = 'merge_increase_data_router'){
        $key = '';
        switch ( $interval ) {
            case self::MERGE_INCREASE_INTERVAL_SECOND_60:
                $key .= self::MERGE_INCREASE_INTERVAL_SECOND_60 . '_';
                break;
            default:
                return false;
        }
        if ( YII_ENV != 'prod' ) {
            Util::systemCall(array_merge([$handle], $data), $queue);
            return true;
        }
        $hashStr = '';
        foreach ( $data as $uk => $u ) {
            if ( in_array($uk, $value) || in_array($uk, $ignore) ) {
                continue;
            }
            $hashStr .= (string)$u;
        }
        $hashStr = md5($hashStr);
        $n = count($hashStr);
        $hash = 0;
        for ( $i = 0; $i < $n; $i++ ) {
            $word = $hashStr{$n};
            if ( ! is_numeric($word) ) {
                continue;
            }
            $hash += $word;
        }
        $hash = $hash % self::MERGE_INCREASE_QUEUE_COUNT;
        $key .= $hash;
        $pack = json_encode(['handle' => $handle, 'data' => $data, 'value' => $value, 'ignore' => $ignore, 'queue' => $queue]);
        $cache = self::cache('MergeIncrease');
        $cache->lPush($key, $pack);
    }

    public static function isBackend() {
        return defined('YII_FRONTEND') && (YII_FRONTEND === 'backend' || YII_FRONTEND === 'famousbackend');
    }

    public static function base64UrlEncode($data)
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    public static function base64UrlDecode($data)
    {
        return base64_decode(str_pad(strtr($data, '-_', '+/'), strlen($data) % 4, '=', STR_PAD_RIGHT));
    }

    public static function getFirstCharter($str)
    {
        if(empty($str)) return null;

        $AsChar = ord($str{0});
        if($AsChar >= ord('A') && $AsChar <= ord('z')) return strtoupper($str{0});
        $s1 = iconv('UTF-8','GBK//IGNORE', $str);
        $s2 = iconv('GBK','UTF-8//IGNORE', $s1);
        $s = $s2==$str ? $s1 : $str;

        $asc=ord($s{0}) * 256 + ord($s{1}) - 65536;
        if($asc>=-20319 && $asc<=-20284) return 'A';
        if($asc>=-20283 && $asc<=-19776) return 'B';
        if($asc>=-19775 && $asc<=-19219) return 'C';
        if($asc>=-19218 && $asc<=-18711) return 'D';
        if($asc>=-18710 && $asc<=-18527) return 'E';
        if($asc>=-18526 && $asc<=-18240) return 'F';
        if($asc>=-18239 && $asc<=-17923) return 'G';
        if($asc>=-17922 && $asc<=-17418) return 'H';
        if($asc>=-17417 && $asc<=-16475) return 'J';
        if($asc>=-16474 && $asc<=-16213) return 'K';
        if($asc>=-16212 && $asc<=-15641) return 'L';
        if($asc>=-15640 && $asc<=-15166) return 'M';
        if($asc>=-15165 && $asc<=-14923) return 'N';
        if($asc>=-14922 && $asc<=-14915) return 'O';
        if($asc>=-14914 && $asc<=-14631) return 'P';
        if($asc>=-14630 && $asc<=-14150) return 'Q';
        if($asc>=-14149 && $asc<=-14091) return 'R';
        if($asc>=-14090 && $asc<=-13319) return 'S';
        if($asc>=-13318 && $asc<=-12839) return 'T';
        if($asc>=-12838 && $asc<=-12557) return 'W';
        if($asc>=-12556 && $asc<=-11848) return 'X';
        if($asc>=-11847 && $asc<=-11056) return 'Y';
        if($asc>=-11055 && $asc<=-10247) return 'Z';

        return null;
    }

    public static function getStrLetterIndex($str)
    {
        $indexArr = ['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J', 'K', 'L', 'M', 'N', 'O', 'P', 'Q', 'R', 'S', 'T', 'U', 'V', 'W', 'X', 'Y', 'Z'];
        $firstCharter = self::getFirstCharter($str);

        return $firstCharter && in_array($firstCharter, $indexArr) ? $firstCharter : '#';
    }

    public static function sendUnicom($url, $params) {
        $appkey     = '3000004872';
        $security   = '2CA10122102ED6D0';
        $params['appkey'] 		= $appkey;
        $params['timestamp'] 	= date('YmdHis');
        ksort($params);
        $params_str = '';
        foreach ( $params as $k => $v ) {
            $params_str .= $k . $v;
        }
        $md5str = $params_str . $security;
        $params['digest'] = strtoupper(md5($md5str));
        $url .= '?' . http_build_query($params);
        $result = self::curl($url, '', 0, [], '', 6, 6);
        $result = json_decode($result, true);
        return $result;
    }

    public static function goApiSend($router, $data, $host = 'http://api.gw.consul.app-echo.com') {
        $now = time();

        $url = $host . $router;

        $headers = [
            'x-timestamp: ' . $now,
            'x-t: ' . md5(self::GO_API_PKEY . $now),
            'Content-Type: application/json',
        ]; // auth

        $r = self::curl($url, json_encode($data), 'post', $headers);

        if ( ! Util::isJson($r) ) {
            Yii::error("goApiSend error, reason: not json, host:{$host}, router:{$router}, data:" . var_dump($data, 1) . ", result:{$r}");
        }
        $r = json_decode($r, true);

        if ( $r['status'] != 1 || (! isset($r['data'])) ) {
            Yii::error("goApiSend error, reason: status failed, host:{$host}, router:{$router}, data:" . var_dump($data, 1) . ", result:" . var_dump($r, 1));
            return null;
        }

        return $r['data'];

    }

    /**
     * 是否是生成环境
     * @return boolean                  [description]
     */
    public static function isProEnv(){

        return (YII_ENV!='dev' && YII_ENV !='stage');
    }

    public static function isEnableFansGroup($userId = 0)
    {
        if(!$userId) {
            return false;
        }

        if($userId == EchoUser::OFFICIAL_USER_ID) {
            return true;
        }

        $createdFansGroupUserIds = EchoGroup::find()
            ->select('created_user_id')
            ->where(['status' => EchoGroup::STATUS_APPROVED])
            ->limit(1000)
            ->asArray()
            ->column();
        $createdFansGroupUserIds = array_diff($createdFansGroupUserIds, [EchoUser::OFFICIAL_USER_ID]);

        if(EchoUser::isConfirmFamousUser($userId)) {
            return true;
        }
        else {
            foreach ($createdFansGroupUserIds as $createdFansGroupUserId) {
                if(SpaFollow::checkFollowing($userId, $createdFansGroupUserId)) {
                    return true;
                    break;
                }
            }
        }

        return false;
    }

    public static function isShowFansGroup($userId = 0)
    {
        if(self::getSource() == 3) {
            return true;
        }
        else {
            if(!$userId) {
                return false;
            }

            return  EchoChooseFansGroup::getChoice($userId) == EchoChooseFansGroup::USER_CHOICE_ACCEPT;
        }

//        if(!$userId) {
//            return false;
//        }
//
//        $allGroupId = EchoUserGroup::getAllGroupIdByUserId($userId);
//        $userChoice = EchoChooseFansGroup::getChoice($userId);
//
//        return $allGroupId ||
//            $userChoice == EchoChooseFansGroup::USER_CHOICE_ACCEPT ||
//            self::isEnableFansGroup($userId);
    }

}
