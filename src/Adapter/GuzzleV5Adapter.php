<?php

namespace DigitalOceanV2\Adapter;

use DigitalOceanV2\Exception\ExceptionInterface;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Event\CompleteEvent;
use GuzzleHttp\Message\Response;
use GuzzleHttp\Message\ResponseInterface;

class GuzzleV5Adapter extends AbstractAdapter implements AdapterInterface
{
    /**
     * @var ClientInterface
     */
    protected $client;

    /**
     * @var ResponseInterface
     */
    protected $response;

    /**
     * @var ExceptionInterface
     */
    protected $exception;

    /**
     * @param string             $accessToken
     * @param ClientInterface    $client      (optional)
     * @param ExceptionInterface $exception   (optional)
     */
    public function __construct($accessToken, ClientInterface $client = null, ExceptionInterface $exception = null)
    {
        $that            = $this;
        $this->client    = $client ?: new \GuzzleHttp\Client();
        $this->exception = $exception;

        $this->client->setDefaultOption('headers/Authorization', sprintf('Bearer %s', $accessToken));

        $this->client->getEmitter()->on('complete', function(CompleteEvent $e) use ($that){
            $that->handleResponse($e);
            $e->stopPropagation();
        });

    }

    /**
     * {@inheritdoc}
     */
    public function get($url)
    {
        $this->response = $this->client->get($url);

        return $this->response->getBody(true);
    }

    /**
     * {@inheritdoc}
     */
    public function delete($url, array $headers = array())
    {
        $this->response = $this->client->delete($url, $headers);

        return $this->response->getBody(true);
    }

    /**
     * {@inheritdoc}
     */
    public function put($url, array $headers = array(), $content = '')
    {
        $headers['content-type'] = 'application/json';
        $request = $this->client->put($url, $headers, $content);
        $this->response = $request;

        return $this->response->getBody(true);
    }

    /**
     * {@inheritdoc}
     */
    public function post($url, array $headers = array(), $content = '')
    {
        $headers['content-type'] = 'application/json';
        $request = $this->client->post($url, $headers, $content);
        $this->response = $request;

        return $this->response->getBody(true);
    }

    /**
     * {@inheritdoc}
     */
    public function getLatestResponseHeaders()
    {
        if (null === $this->response) {
            return null;
        }

        return array(
            'reset'     => (integer) (string) $this->response->getHeader('RateLimit-Reset'),
            'remaining' => (integer) (string) $this->response->getHeader('RateLimit-Remaining'),
            'limit'     => (integer) (string) $this->response->getHeader('RateLimit-Limit'),
        );
    }

    /**
     * @param  CompleteEvent                                $event
     * @throws \RuntimeException|ExceptionInterface
     */
    protected function handleResponse(CompleteEvent $event)
    {
        $this->response = $event->getResponse();

        if ($this->response->getStatusCode() === '200') {
            return;
        }

        $body = $this->response->getBody(true);
        $code = $this->response->getStatusCode();

        if ($this->exception) {
            throw $this->exception->create($body, $code);
        }

        $content = $this->response->json();

        throw new \RuntimeException(sprintf('[%d] %s (%s)', $code, $content->message, $content->id), $code);
    }
}
