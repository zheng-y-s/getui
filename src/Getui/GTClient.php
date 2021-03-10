<?php
namespace Getui;

use Getui\utils\GTHttpManager;
use Getui\utils\GTConfig;
use Getui\request\auth\GTAuthRequest;
use Getui\request\push\android\GTAndroid;
use Getui\request\push\android\GTThirdNotification;
use Getui\request\push\android\GTUps;
use Getui\request\push\ios\GTAlert;
use Getui\request\push\ios\GTAps;
use Getui\request\push\ios\GTIos;
use Getui\request\push\ios\GTMultimedia;
use Getui\request\push\GTAudienceRequest;
use Getui\request\push\GTCondition;
use Getui\request\push\GTNotification;
use Getui\request\push\GTPushBatchRequest;
use Getui\request\push\GTPushChannel;
use Getui\request\push\GTPushRequest;
use Getui\request\push\GTRevoke;
use Getui\request\push\GTSettings;
use Getui\request\push\GTStrategy;
use Getui\request\user\GTAliasRequest;
use Getui\request\user\GTBadgeSetRequest;
use Getui\request\user\GTTagBatchSetRequest;
use Getui\request\user\GTTagSetRequest;
use Getui\request\user\GTUserQueryRequest;
use Getui\request\GTApiRequest;
use Getui\GTPushApi;
use Getui\GTStatisticsApi;
use Getui\GTUserApi;

/**
 * 个推客户端，用于进行推送、用户设置、报表查询等操作
 * 每new一个GTclient对象，会进行一次鉴权，建议用户保存GTclient对象重复使用。
 **/
class GTClient
{
	//第三方 标识
	private $appkey;
	//第三方 密钥
	private $masterSecret;
	//鉴权token
	private $authToken;
	//第三方 appid
	private $appId;

	//是否使用https连接 以该标志为准
	private $useSSL = null;
	//用户配置或者内置域名列表
	private $domainUrlList = null;
	//是否指定域名。如果指定，使用过程中域名不会发生变化
	private $isAssigned = false;

	//推送api
	private $pushApi = null;
	//报表api
	private $statisticsApi = null;
	//用户api
	private $userApi = null;

	public function __construct($domainUrl, $appkey, $appId, $masterSecret, $ssl = NULL)
	{
		$this->appkey = $appkey;
		$this->masterSecret = $masterSecret;
		$this->appId = $appId;
		$this->pushApi = new GTPushApi($this);
		$this->statisticsApi = new GTStatisticsApi($this);
		$this->userApi = new GTUserApi($this);

		$domainUrl = trim($domainUrl);
		if ($ssl == NULL && $domainUrl != NULL && strpos(strtolower($domainUrl), "https:") === 0) {
			$ssl = true;
		}

		$this->useSSL = ($ssl == NULL ? false : $ssl);

		if ($domainUrl == NULL || strlen($domainUrl) == 0) {
			$this->domainUrlList = GTConfig::getDefaultDomainUrl($this->useSSL);
		} else {
			if (GTConfig::isNeedOSAsigned()) {
				$this->isAssigned = true;
			}
			$this->domainUrlList = array($domainUrl);
		}
		//鉴权
		try {
			$this->auth($appkey,$masterSecret);
		} catch (Exception $e) {
			echo  $e->getMessage();
		}
	}

	public function getAuthToken()
	{
		return $this->authToken;
	}

	public function setAuthToken($authToken)
	{
		$this->authToken = $authToken;
	}

	public function pushApi()
	{
		return $this->pushApi;
	}

	public function statisticsApi()
	{
		return $this->statisticsApi;
	}

	public function userApi()
	{
		return $this->userApi;
	}


	public function getHost()
	{
		return $this->domainUrlList[0];
	}

	public function setDomainUrlList($domainUrlList)
	{
		$this->domainUrlList = $domainUrlList;
	}

	public function getAppId()
	{
		return $this->appId;
	}

	public function auth($appkey,$masterSecret)
	{
		$auth = new GTAuthRequest();
		$auth->setAppkey($appkey);
		$timeStamp = $this->getMicroTime();
		$sign = hash("sha256", $appkey . $timeStamp . $masterSecret);
		$auth->setSign($sign);
		$auth->setTimestamp($timeStamp);
		$rep = $this->userApi()->auth($auth);
		if ($rep["code"] == 0) {
			$this->authToken = $rep["data"]["token"];
			return true;
		}
		return false;
	}

	function getMicroTime()
	{
		list($usec, $sec) = explode(" ", microtime());
		$time = ($sec . substr($usec, 2, 3));
		return $time;
	}
}