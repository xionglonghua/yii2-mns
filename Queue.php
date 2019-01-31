<?php
namespace koenigseggposche\yii2mns;

use Yii;
use yii\base\Component;
use AliyunMNS\Exception\MnsException;
use AliyunMNS\Requests\SendMessageRequest;
use AliyunMNS\Model\SendMessageRequestItem;
use AliyunMNS\Requests\BatchSendMessageRequest;
use AliyunMNS\Requests\BatchReceiveMessageRequest;

class Queue extends Component
{
    public $client;
    public $name;

    private $queue;

    public function init()
    {
        parent::init();
        $this->queue = $this->client->getQueueRef($this->name);
    }

    public function __call($method_name, $args)
    {
        if (method_exists($this->queue, $method_name)) {
            return call_user_func_array([$this->queue, $method_name], $args);
        } else {
            return parent::__call($method_name, $args);
        }
    }

    /**
     * 同步发送
     * @param $messageBody 消息内容
     * @return bool 成功或失败
     */
    public function send($messageBody, $delaySeconds = NULL)
    {
        try {
            $request = new SendMessageRequest($messageBody, $delaySeconds);
            $this->queue->sendMessage($request);
            return true;
        } catch (MnsException $e) {
            Yii::error("消息发送错误({$e->getMnsErrorCode()}): {$e->getRequestId()}\n{$e->getMessage()}\n{$e->getTraceAsString()}}", 'mns.send');
        }
        return false;
    }

    /**
     * 同步发送
     * @param $messageBody 消息内容
     * @return bool 成功或失败
     */
    public function sendBatch($messageBodys, $delaySeconds = NULL, $priority = NULL)
    {
        try {
            $messageBodys = (array) $messageBodys;
            $messageBodyss = array_chunk($messageBodys, 16);
            foreach ($messageBodyss as $messageBodys) {
                $items = [];
                foreach ($messageBodys as $messageBody) {
                    $items[] = new SendMessageRequestItem($messageBody, $delaySeconds, $priority);
                }
                $request = new BatchSendMessageRequest($items);
                $this->queue->batchSendMessage($request);
            }
            return true;
        } catch (MnsException $e) {
            Yii::error("消息发送错误({$e->getMnsErrorCode()}): {$e->getRequestId()}\n{$e->getMessage()}\n{$e->getTraceAsString()}}", 'mns.send_batch');
        }
        return false;
    }

    /**
     * 接收消息
     * @param $waitSeconds 等待的秒数
     * @return string 消息内容
     */
    public function receive($waitSeconds = 10)
    {
        try {
            $res = $this->queue->receiveMessage($waitSeconds);
            $messageBody = $res->getMessageBody();
            $receiptHandle = $res->getReceiptHandle();
            $this->queue->deleteMessage($receiptHandle);
            return $messageBody;
        } catch (MnsException $e) {
            if ($e->getMnsErrorCode() != 'MessageNotExist') {
                Yii::error("消息发送错误({$e->getMnsErrorCode()}): {$e->getRequestId()}\n{$e->getMessage()}\n{$e->getTraceAsString()}}", 'mns.receive');
            }
        }
        return null;
    }

    /**
     * 批量接收消息
     * @param $waitSeconds 等待的秒数
     * @return string 消息内容
     */
    public function receiveBatch($numOfMessages = 16, $waitSeconds = 10)
    {
        $messageBodys = [];
        try {
            for ($i = 0; $i < $numOfMessages; $i += 16) {
                // 批量读取数据
                $messageHandlers = [];
                $request = new BatchReceiveMessageRequest(min($numOfMessages - $i, 16), $waitSeconds);
                $res = $this->queue->batchReceiveMessage($request);
                $messages = $res->getMessages();
                foreach ($messages as $message) {
                    $messageBodys[] = $message->getMessageBody();
                    $messageHandlers[] = $message->getReceiptHandle();
                }
                // 批量删除消息
                if ($messageHandlers) {
                    $this->queue->batchDeleteMessage($messageHandlers);
                }
            }
        } catch (MnsException $e) {
            if ($e->getMnsErrorCode() != 'MessageNotExist') {
                Yii::error("消息接收错误({$e->getMnsErrorCode()}): {$e->getRequestId()}\n{$e->getMessage()}\n{$e->getTraceAsString()}}", 'mns.receive_batch');
            }
        }
        return $messageBodys;
    }
}
