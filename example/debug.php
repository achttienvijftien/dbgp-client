<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use DbgpClient\Server;
use DbgpClient\Type\BreakpointType;

$server = new Server('127.0.0.1', 9003);
$server->listen();

echo "Waiting for Xdebug connection on 127.0.0.1:9003...\n";
echo "Run: php -d xdebug.mode=debug -d xdebug.start_with_request=yes -d xdebug.client_port=9003 example/target.php\n\n";

$session = $server->accept(30.0);

if ($session === null) {
    echo "Timeout waiting for connection\n";
    exit(1);
}

echo "Connected!\n";
echo "Engine: {$session->init->engine->name} {$session->init->engine->version}\n";
echo "File: {$session->init->fileUri}\n\n";

$status = $session->status();
echo "Status: {$status->status->value}\n";

$bp = $session->breakpointSet(BreakpointType::Line, $session->init->fileUri, 5);
echo "Breakpoint set: id={$bp->id}\n";

echo "Running to breakpoint...\n";
$response = $session->run();
echo "Status: {$response->status->value}, reason: {$response->reason->value}\n";

if ($response->status->value === 'break') {
    $stack = $session->stackGet();
    echo "\nStack:\n";
    foreach ($stack->stack as $frame) {
        echo "  #{$frame->level} {$frame->filename}:{$frame->lineno}";
        if ($frame->where) {
            echo " in {$frame->where}";
        }
        echo "\n";
    }

    $context = $session->contextGet();
    echo "\nVariables:\n";
    foreach ($context->properties as $prop) {
        echo "  {$prop->name} ({$prop->type}) = {$prop->value}\n";
    }

    $eval = $session->eval('$x + $y');
    if (!empty($eval->properties)) {
        echo "\nEval '\$x + \$y': {$eval->properties[0]->value}\n";
    }
}

echo "\nContinuing...\n";
$response = $session->run();
echo "Final status: {$response->status->value}\n";

$session->close();
$server->close();
