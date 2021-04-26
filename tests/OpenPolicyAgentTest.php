<?php

namespace BuildSecurity\OpenPolicyAgentBundle\Tests;

use Symfony\Component\HttpKernel\Event\KernelEvent;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

use BuildSecurity\OpenPolicyAgentBundle\Authorize;
use BuildSecurity\OpenPolicyAgentBundle\OpenPolicyAgent;
use PHPUnit\Framework\TestCase;

use Psr\Log\LoggerInterface;

class MockController
{
    #[Authorize('request.label')]
    public function authorizedMethod(): Response
    {
        return new Response(
            '<html><body>Authorized!</body></html>'
        );
    }

    public function nonAuthorizedMethod(): Response
    {
        return new Response(
            '<html><body>Authorization not required...</body><html>'
        );
    }
}

class MockControllerEvent extends KernelEvent
{
    public function getController() {
    }
}

class OpenPolicyAgentTest extends TestCase
{
    private OpenPolicyAgent $listener;
 
    protected function setUp(): void {
        $loggerInterfaceMock = $this->getMockBuilder(LoggerInterface::class)
            ->disableOriginalConstructor()
            ->getMock();
        
        $this->pdp_config = array(
            'port' => 8181,
            'hostname' => 'http://localhost',
            'policy.path' => '/allow',
            'readTimeout.milliseconds' => 5000,
            'connectionTimeout.milliseconds' => 5000,
            'retry.maxAttempts' => 2,
            'retry.backoff.milliseconds' => 1000,
        );

        $callback = function ($method, $url, $options) {
            if ($method == 'POST') {
                switch ($url) {
                    case 'http://localhost:8181/v1/data/allow':
                        return new MockResponse(json_encode(
                            array(
                                'result' => array(
                                    'allow' => $this->allow,
                                ),
                            ),
                        ));
                        break;
                }
            }

            return new MockResponse(null, ['http_code' => 404]);
        };

        $this->client = new MockHttpClient($callback);

        $this->listener = new OpenPolicyAgent(
            $this->pdp_config,
            $this->client,
        );

        $this->listener->setLogger($loggerInterfaceMock);
    }
 
    protected function tearDown(): void {
        unset($this->listener);
    }
    
    public function testWithoutAttributeOnController()
    {
        $eventMock = $this->getMockBuilder(MockControllerEvent::class)
            ->disableOriginalConstructor()
            ->getMock();
 
        $controllerMock = new MockController();

        $requestMock = Request::create('http://endpoint.org/', 'GET', array('param' => 'value'));
 
        $eventMock
            ->expects($this->once())
            ->method('getController')
            ->willReturn(array($controllerMock, 'nonAuthorizedMethod'));
 
        $eventMock
            ->expects($this->never())
            ->method('getRequest')
            ->willReturn($requestMock);

        $result = $this->listener->onKernelController($eventMock);
    }

    public function testDenyResponse()
    {
        $eventMock = $this->getMockBuilder(MockControllerEvent::class)
            ->disableOriginalConstructor()
            ->getMock();
 
        $controllerMock = new MockController();

        $requestMock = Request::create('http://endpoint.org/', 'GET', array('param' => 'value'));
 
        $eventMock
            ->expects($this->once())
            ->method('getController')
            ->willReturn(array($controllerMock, 'authorizedMethod'));
 
        $eventMock
            ->expects($this->once())
            ->method('getRequest')
            ->willReturn($requestMock);
 
        $this->allow = false;
        $this->expectException(AccessDeniedHttpException::class);

        $result = $this->listener->onKernelController($eventMock);
    }

    public function testAllowResponse()
    {
        $eventMock = $this->getMockBuilder(MockControllerEvent::class)
            ->disableOriginalConstructor()
            ->getMock();
 
        $controllerMock = new MockController();

        $requestMock = Request::create('http://endpoint.org/', 'GET', array('param' => 'value'));
 
        $eventMock
            ->expects($this->once())
            ->method('getController')
            ->willReturn(array($controllerMock, 'authorizedMethod'));
 
        $eventMock
            ->expects($this->once())
            ->method('getRequest')
            ->willReturn($requestMock);
 
        $this->allow = true;

        $result = $this->listener->onKernelController($eventMock);
    }
}