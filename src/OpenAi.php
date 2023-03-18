<?php

namespace lyhiving\OpenAi;

use Exception;

class OpenAi
{
    private string $engine = "davinci";
    private string $model = "text-davinci-002";
    private string $chatModel = "gpt-3.5-turbo";
    private array $headers;
    private array $contentTypes;
    private int $timeout = 0;
    private  $stream_method = "";
    private string $customUrl = "";
    private string $proxy = "";
    private array $curlInfo = [];

    private bool $curlNoHttpsCheck = true;
    private int $curlRetryMaxTimes = 2;
    private bool $comFix = true;


    public function __construct($OPENAI_API_KEY)
    {
        $this->contentTypes = [
            "application/json"    => "Content-Type: application/json",
            "multipart/form-data" => "Content-Type: multipart/form-data",
        ];

        $this->headers = [
            $this->contentTypes["application/json"],
            "Authorization: Bearer $OPENAI_API_KEY",
        ];
    }


    /**
     * @param $opt
     * @return object
     */
    public function setNohttps($opt){
        $this->curlNoHttpsCheck = $opt;
        return $this;
    }

    /**
     * @param $opt
     * @return object
     */
    public function setRetryTimes($opt){
        $this->curlRetryMaxTimes = $opt;
        return $this;
    }

    /**
     * @param $opt
     * @return object
     */
    public function setConFix($opt){
        $this->comFix = $opt;
        return $this;
    }

    
    /**
     * @return array
     * Remove this method from your code before deploying
     */
    public function getCURLInfo()
    {
        return $this->curlInfo;
    }

    /**
     * @return bool|string
     */
    public function listModels()
    {
        $url = Url::fineTuneModel();
        $this->baseUrl($url);

        return $this->sendRequest($url, 'GET');
    }

    /**
     * @param $model
     * @return bool|string
     */
    public function retrieveModel($model)
    {
        $model = "/$model";
        $url   = Url::fineTuneModel().$model;
        $this->baseUrl($url);

        return $this->sendRequest($url, 'GET');
    }

    /**
     * @param $opts
     * @return bool|string
     * @deprecated
     */
    public function complete($opts)
    {
        $engine = $opts['engine'] ?? $this->engine;
        $url    = Url::completionURL($engine);
        unset($opts['engine']);
        $this->baseUrl($url);

        return $this->sendRequest($url, 'POST', $opts);
    }

    /**
     * @param        $opts
     * @param  null  $stream
     * @return bool|string
     * @throws Exception
     */
    public function completions($opts, $stream = null)
    {
        if ($stream != null && array_key_exists('stream', $opts)) {
            if (!$opts['stream']) {
                throw new Exception(
                    'Please provide a stream function. Check https://github.com/orhanerday/open-ai#stream-example for an example.'
                );
            }

            $this->stream_method = $stream;
        }

        $opts['model'] = $opts['model'] ?? $this->model;
        $url           = Url::completionsURL();
        $this->baseUrl($url);

        
        $isMyAI = false;
        if(strpos($opts['model'],'@')){
            $models = explode('@', $opts['model']);
            $case = $models[1];
            if(isset($_ENV['ROUTER_PATH']) && $_ENV['ROUTER_PATH']){
                $rfile = $_ENV['ROUTER_PATH'].'/'.$case.'.ai.php';
                if(is_readable($rfile)){
                    $isMyAI = true;
                    require_once $rfile;
                    $_case = $case.'AI';
                    $myAI =  new $_case();
                    $result = $myAI->run($opts);
                }
            }
            $opts['model'] = $models[0];
        }
        if(!$isMyAI) $result = $this->sendRequest($url, 'POST', $opts);
        if(!$result) return $result;
        if(!$this->comFix) return $result;
        $res = json_decode($result);
        if(!$res||!$res->id||!$res->choices) return $result;
        $hadwrap = false;
        foreach($res->choices as $k=>$v){
            $text = $v->text;
            $text = str_replace( "\n", '<br />', $text ); 
            if(!$text) return $result;
            if(strpos($text,"<br /><br />")===0||strpos($text,"<br /><br />")===false) return $result;
            $texts = explode('<br /><br />', $text); 
            $dots =  array('…', '……', '?', '？', '!', '！', '.', '。', ';', '；', '吗', '么', '嘛', '吧', '呢', '呀', '哦', '唉', '嗯', '哈', '怎么样', '么样', '样', '什么', '多少', '少');
            if($texts && $texts[0] &&(
                mb_strlen($texts[0]) <=8 ||
                $texts[0] =='' ||
                in_array($texts[0], $dots) ||
                in_array(mb_substr($texts[0],0,1), $dots) ||
                in_array(mb_substr($texts[0],-1), $dots) ||
                in_array(mb_substr($texts[0],0,2), $dots) ||
                in_array(mb_substr($texts[0],-2), $dots) 
            )){
                unset($texts[0]);
                $text = count($texts) ==1 ? $texts[1] : implode('<br /><br />', $texts);
                $text = str_replace('<br />', "\n", $text );
                $text = trim($text);
                $res->choices[$k]->text = $text;
                $hadwrap = true;
            }
        }
        if(!$hadwrap) return $result;
        $result = json_encode($res, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);
        return $result;
    }


        /**
     * @param        $opts
     * @param  null  $stream
     * @return bool|string
     * @throws Exception
     */
    public function completion($opts, $stream = null)
    {
        if ($stream != null && array_key_exists('stream', $opts)) {
            if (!$opts['stream']) {
                throw new Exception(
                    'Please provide a stream function. Check https://github.com/orhanerday/open-ai#stream-example for an example.'
                );
            }

            $this->stream_method = $stream;
        }

        $opts['model'] = $opts['model'] ?? $this->model;
        $url           = Url::completionsURL();
        $this->baseUrl($url);

        return $this->sendRequest($url, 'POST', $opts);
    }

    /**
     * @param $opts
     * @return bool|string
     */
    public function createEdit($opts)
    {
        $url = Url::editsUrl();
        $this->baseUrl($url);

        return $this->sendRequest($url, 'POST', $opts);
    }

    /**
     * @param $opts
     * @return bool|string
     */
    public function image($opts)
    {
        $url = Url::imageUrl()."/generations";
        $this->baseUrl($url);

        return $this->sendRequest($url, 'POST', $opts);
    }

    /**
     * @param $opts
     * @return bool|string
     */
    public function imageEdit($opts)
    {
        $url = Url::imageUrl()."/edits";
        $this->baseUrl($url);

        return $this->sendRequest($url, 'POST', $opts);
    }

    /**
     * @param $opts
     * @return bool|string
     */
    public function createImageVariation($opts)
    {
        $url = Url::imageUrl()."/variations";
        $this->baseUrl($url);

        return $this->sendRequest($url, 'POST', $opts);
    }

    /**
     * @param $opts
     * @return bool|string
     * @deprecated
     */
    public function search($opts)
    {
        $engine = $opts['engine'] ?? $this->engine;
        $url    = Url::searchURL($engine);
        unset($opts['engine']);
        $this->baseUrl($url);

        return $this->sendRequest($url, 'POST', $opts);
    }

    /**
     * @param $opts
     * @return bool|string
     * @deprecated
     */
    public function answer($opts)
    {
        $url = Url::answersUrl();
        $this->baseUrl($url);

        return $this->sendRequest($url, 'POST', $opts);
    }

    /**
     * @param $opts
     * @return bool|string
     * @deprecated
     */
    public function classification($opts)
    {
        $url = Url::classificationsUrl();
        $this->baseUrl($url);

        return $this->sendRequest($url, 'POST', $opts);
    }

    /**
     * @param $opts
     * @return bool|string
     */
    public function moderation($opts)
    {
        $url = Url::moderationUrl();
        $this->baseUrl($url);

        return $this->sendRequest($url, 'POST', $opts);
    }

    /**
     * @param        $opts
     * @param  null  $stream
     * @return bool|string
     * @throws Exception
     */
    public function chat($opts, $stream = null)
    {
        if ($stream != null && array_key_exists('stream', $opts)) {
            if (!$opts['stream']) {
                throw new Exception(
                    'Please provide a stream function. Check https://github.com/lyhiving/open-ai#stream-example for an example.'
                );
            }

            $this->stream_method = $stream;
        }

        $opts['model'] = $opts['model'] ?? $this->chatModel;
        $url           = Url::chatUrl();
        $this->baseUrl($url);


        $isMyAI = false;
        if(strpos($opts['model'],'@')){
            $models = explode('@', $opts['model']);
            $case = $models[1];
            if(isset($_ENV['ROUTER_PATH']) && $_ENV['ROUTER_PATH']){
                $rfile = $_ENV['ROUTER_PATH'].'/'.$case.'.chatai.php';
                if(is_readable($rfile)){
                    $isMyAI = true;
                    require_once $rfile;
                    $_case = $case.'AI';
                    $myAI =  new $_case();
                    $result = $myAI->run($opts);
                }
            }
            $opts['model'] = $models[0];
        }
        if(!$isMyAI) $result = $this->sendRequest($url, 'POST', $opts);
        if(!$result) return $result;
        if(!$this->comFix) return $result;
        $res = json_decode($result);
        if(!$res||!$res->id||!$res->choices) return $result;
        $hadwrap = false;
        foreach($res->choices as $k=>$v){
            $text = $v->text;
            $text = str_replace( "\n", '<br />', $text ); 
            if(!$text) return $result;
            if(strpos($text,"<br /><br />")===0||strpos($text,"<br /><br />")===false) return $result;
            $texts = explode('<br /><br />', $text); 
            $dots =  array('…', '……', '?', '？', '!', '！', '.', '。', ';', '；', '吗', '么', '嘛', '吧', '呢', '呀', '哦', '唉', '嗯', '哈', '怎么样', '么样', '样', '什么', '多少', '少');
            if($texts && $texts[0] &&(
                mb_strlen($texts[0]) <=8 ||
                $texts[0] =='' ||
                in_array($texts[0], $dots) ||
                in_array(mb_substr($texts[0],0,1), $dots) ||
                in_array(mb_substr($texts[0],-1), $dots) ||
                in_array(mb_substr($texts[0],0,2), $dots) ||
                in_array(mb_substr($texts[0],-2), $dots) 
            )){
                unset($texts[0]);
                $text = count($texts) ==1 ? $texts[1] : implode('<br /><br />', $texts);
                $text = str_replace('<br />', "\n", $text );
                $text = trim($text);
                $res->choices[$k]->text = $text;
                $hadwrap = true;
            }
        }
        if(!$hadwrap) return $result;
        $result = json_encode($res, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);
        return $result;
    }

    /**
     * @param $opts
     * @return bool|string
     */
    public function transcribe($opts)
    {
        $url = Url::transcriptionsUrl();
        $this->baseUrl($url);

        return $this->sendRequest($url, 'POST', $opts);
    }

    /**
     * @param $opts
     * @return bool|string
     */
    public function translate($opts)
    {
        $url = Url::translationsUrl();
        $this->baseUrl($url);

        return $this->sendRequest($url, 'POST', $opts);
    }

    /**
     * @param $opts
     * @return bool|string
     */
    public function uploadFile($opts)
    {
        $url = Url::filesUrl();
        $this->baseUrl($url);

        return $this->sendRequest($url, 'POST', $opts);
    }

    /**
     * @return bool|string
     */
    public function listFiles()
    {
        $url = Url::filesUrl();
        $this->baseUrl($url);

        return $this->sendRequest($url, 'GET');
    }

    /**
     * @param $file_id
     * @return bool|string
     */
    public function retrieveFile($file_id)
    {
        $file_id = "/$file_id";
        $url     = Url::filesUrl().$file_id;
        $this->baseUrl($url);

        return $this->sendRequest($url, 'GET');
    }

    /**
     * @param $file_id
     * @return bool|string
     */
    public function retrieveFileContent($file_id)
    {
        $file_id = "/$file_id/content";
        $url     = Url::filesUrl().$file_id;
        $this->baseUrl($url);

        return $this->sendRequest($url, 'GET');
    }

    /**
     * @param $file_id
     * @return bool|string
     */
    public function deleteFile($file_id)
    {
        $file_id = "/$file_id";
        $url     = Url::filesUrl().$file_id;
        $this->baseUrl($url);

        return $this->sendRequest($url, 'DELETE');
    }

    /**
     * @param $opts
     * @return bool|string
     */
    public function createFineTune($opts)
    {
        $url = Url::fineTuneUrl();
        $this->baseUrl($url);

        return $this->sendRequest($url, 'POST', $opts);
    }

    /**
     * @return bool|string
     */
    public function listFineTunes()
    {
        $url = Url::fineTuneUrl();
        $this->baseUrl($url);

        return $this->sendRequest($url, 'GET');
    }

    /**
     * @param $fine_tune_id
     * @return bool|string
     */
    public function retrieveFineTune($fine_tune_id)
    {
        $fine_tune_id = "/$fine_tune_id";
        $url          = Url::fineTuneUrl().$fine_tune_id;
        $this->baseUrl($url);

        return $this->sendRequest($url, 'GET');
    }

    /**
     * @param $fine_tune_id
     * @return bool|string
     */
    public function cancelFineTune($fine_tune_id)
    {
        $fine_tune_id = "/$fine_tune_id/cancel";
        $url          = Url::fineTuneUrl().$fine_tune_id;
        $this->baseUrl($url);

        return $this->sendRequest($url, 'POST');
    }

    /**
     * @param $fine_tune_id
     * @return bool|string
     */
    public function listFineTuneEvents($fine_tune_id)
    {
        $fine_tune_id = "/$fine_tune_id/events";
        $url          = Url::fineTuneUrl().$fine_tune_id;
        $this->baseUrl($url);

        return $this->sendRequest($url, 'GET');
    }

    /**
     * @param $fine_tune_id
     * @return bool|string
     */
    public function deleteFineTune($fine_tune_id)
    {
        $fine_tune_id = "/$fine_tune_id";
        $url          = Url::fineTuneModel().$fine_tune_id;
        $this->baseUrl($url);

        return $this->sendRequest($url, 'DELETE');
    }

    /**
     * @param
     * @return bool|string
     * @deprecated
     */
    public function engines()
    {
        $url = Url::enginesUrl();
        $this->baseUrl($url);

        return $this->sendRequest($url, 'GET');
    }

    /**
     * @param $engine
     * @return bool|string
     * @deprecated
     */
    public function engine($engine)
    {
        $url = Url::engineUrl($engine);
        $this->baseUrl($url);

        return $this->sendRequest($url, 'GET');
    }

    /**
     * @param $opts
     * @return bool|string
     */
    public function embeddings($opts)
    {
        $url = Url::embeddings();
        $this->baseUrl($url);

        return $this->sendRequest($url, 'POST', $opts);
    }

    /**
     * @param  int  $timeout
     */
    public function setTimeout(int $timeout)
    {
        $this->timeout = $timeout;
    }

    /**
     * @param  string  $proxy
     */
    public function setProxy(string $proxy)
    {
        if ($proxy && strpos($proxy, '://') === false) {
            $proxy = 'https://'.$proxy;
        }
        $this->proxy = $proxy;
    }

    /**
     * @param  string  $customUrl
     * @deprecated
     */

    /**
     * @param  string  $customUrl
     * @return void
     */
    public function setCustomURL(string $customUrl)
    {
        if ($customUrl != "") {
            $this->customUrl = $customUrl;
        }
    }

    /**
     * @param  string  $customUrl
     * @return void
     */
    public function setBaseURL(string $customUrl)
    {
        if ($customUrl != '') {
            $this->customUrl = $customUrl;
        }
    }

    /**
     * @param  array  $header
     * @return void
     */
    public function setHeader(array $header)
    {
        if ($header) {
            foreach ($header as $key => $value) {
                $this->headers[$key] = $value;
            }
        }
    }

    /**
     * @param  string  $org
     */
    public function setORG(string $org)
    {
        if ($org != "") {
            $this->headers[] = "OpenAI-Organization: $org";
        }
    }

    /**
     * @param string $url
     * @param string $method
     * @param array $opts
     * @return bool|string
     * must be public for use
     */
    public function doRequest(string $url, string $method, array $opts = [])
    {
        return $this->sendRequest($url, $method, $opts);
    }

        /**
     * @param string $url
     * @param string $method
     * @param array $opts
     * @return bool|string
     */
    public function sendRequest(string $url, string $method, array $opts = [])
    {
        $result = $this->sendRequestNode($url, $method, $opts);
        $response = $result['response'];
        if ($result['errno']!== 0 && $this->curlRetryMaxTimes) {
            for($i=0;$i<$this->curlRetryMaxTimes; $i++){
                usleep(rand(100,400));
                $result = $this->sendRequestNode($url, $method, $opts);
                $response = $result['response'];
                if($result['errno']==0){
                    break;
                }
            }
        }   
        return $response;
    }

    /**
     * @param  string  $url
     * @param  string  $method
     * @param  array   $opts
     * @return bool|string
     */
    private function sendRequestNode(string $url, string $method, array $opts = [])
    {
        $post_fields = json_encode($opts);

        if (array_key_exists('file', $opts) || array_key_exists('image', $opts)) {
            $this->headers[0] = $this->contentTypes["multipart/form-data"];
            $post_fields      = $opts;
        } else {
            $this->headers[0] = $this->contentTypes["application/json"];
        }
        $curl_info = [
            CURLOPT_URL            => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING       => '',
            CURLOPT_MAXREDIRS      => 10,
            CURLOPT_TIMEOUT        => $this->timeout,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION   => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST  => $method,
            CURLOPT_POSTFIELDS     => $post_fields,
            CURLOPT_HTTPHEADER     => $this->headers,
        ];

        if($this->curlNoHttpsCheck){
            $curl_info[CURLOPT_SSL_VERIFYPEER] = false; 
            $curl_info[CURLOPT_SSL_VERIFYHOST] = 0;
            $curl_info[CURLOPT_MAXREDIRS] =2;
        }

        if ($opts == []) {
            unset($curl_info[CURLOPT_POSTFIELDS]);
        }

        if (!empty($this->proxy)) {
            $curl_info[CURLOPT_PROXY] = $this->proxy;
        }


        if (array_key_exists('stream', $opts) && $opts['stream'] && $this->stream_method) {
             $curl_info[CURLOPT_WRITEFUNCTION] = $this->stream_method;
        }

        $curl = curl_init();

        curl_setopt_array($curl, $curl_info);
        $response = curl_exec($curl);

        $info           = curl_getinfo($curl);
        $this->curlInfo = $info;

        $response = curl_exec($curl);
        $curl_errno = curl_errno($curl);
        curl_close($curl);
        return array('errno'=>$curl_errno, 'response'=>$response);
    }

    /**
     * @param  string  $url
     */
    private function baseUrl(string &$url)
    {
        if ($this->customUrl != "") {
            $url = str_replace(Url::ORIGIN, $this->customUrl, $url);
        }
    }
}