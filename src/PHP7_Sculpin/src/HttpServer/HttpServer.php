<?php

declare(strict_types=1);

/*
 * This file is part of Symplify
 * Copyright (c) 2016 Tomas Votruba (http://tomasvotruba.cz).
 */

namespace Symplify\PHP7_Sculpin\HttpServer;

use React\EventLoop\StreamSelectLoop;
use React\Http\Request;
use React\Http\Response;
use React\Http\Server as ReactHttpServer;
use React\Socket\Server as ReactSocketServer;
use Symfony\Component\Console\Output\OutputInterface;

final class HttpServer
{
    /**
     * @var string
     */
    private $outputDirectory;

    /**
     * @var StreamSelectLoop
     */
    private $streamSelectLoop;

    /**
     * @var ReactHttpServer
     */
    private $reactHttpServer;

    /**
     * @var int
     */
    private $port;

    /**
     * @var ReactSocketServer
     */
    private $reactSocketServer;

    /**
     * @var OutputInterface
     */
    private $output;

    /**
     * @var ResponseWriter
     */
    private $responseWriter;

    public function __construct(string $outputDirectory, OutputInterface $output, ResponseWriter $responseWriter)
    {
        $this->outputDirectory = $outputDirectory;
        $this->output = $output;
        $this->responseWriter = $responseWriter;
    }

    public function init(int $port = 8000)
    {
        $this->setupDependencies();

        $this->port = $port;

        $this->reactHttpServer->on('request', function (Request $request, Response $response) {
            $path = $this->outputDirectory . '/' . ltrim(rawurldecode($request->getPath()), '/');
            if (is_dir($path)) {
                $path .= '/index.html';
            }

            if (! file_exists($path)) {
                $this->responseWriter->send404Response($request, $response);
            } else {
                $this->responseWriter->send200Response($request, $response, $path);
            }
        });

        $this->reactSocketServer->listen($this->port, '0.0.0.0');
    }

    public function addPeriodicTimer(int $interval, callable $callback)
    {
        $this->streamSelectLoop->addPeriodicTimer($interval, $callback);
    }

    public function run()
    {
        $this->output->writeln(sprintf(
            'Starting Sculpin at <info>http://localhost:%s</info>',
            $this->port
        ));
        $this->output->writeln('Quit the server with <info>CONTROL-C</info>.');

        $this->streamSelectLoop->run();
    }

    private function setupDependencies()
    {
        $this->streamSelectLoop = new StreamSelectLoop();
        $this->reactSocketServer = new ReactSocketServer($this->streamSelectLoop);
        $this->reactHttpServer = new ReactHttpServer($this->reactSocketServer);
    }
}