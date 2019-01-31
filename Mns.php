<?php
namespace koenigseggposche\yii2mns;

use Yii;
use yii\base\Component;
use AliyunMNS\Client;

class Mns extends Component
{
    public $accessId = '';
    public $accessKey = '';
    public $endpoint = '';

    private $client = null;
    private $queues = [];

    public function init()
    {
        parent::init();
        $this->client = new Client($this->endpoint, $this->accessId, $this->accessKey);
    }

    public function __call($method_name, $args)
    {
        if (method_exists($this->client, $method_name)) {
            return call_user_func_array([$this->client, $method_name], $args);
        } else {
            return parent::__call($method_name, $args);
        }
    }

    public function __get($name)
    {
        if (isset($this->queues[$name])) {
            return $this->queues[$name];
        }
        $params = [
            'class' => Queue::className(),
            'client' => $this->client,
            'name' => $name,
        ];
        $queue = Yii::createObject($params);
        $this->queues[$name] = $queue;
        return $queue;
    }
}