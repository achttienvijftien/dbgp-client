# dbgp-client

A PHP client library for the [DBGp protocol](https://xdebug.org/docs/dbgp), the debugging protocol used by Xdebug.

Provides a clean, typed API for communicating with Xdebug: setting breakpoints, stepping through code, inspecting variables, evaluating expressions, and controlling execution flow.

## Installation

```bash
composer require achttienvijftien/dbgp-client
```

Requires PHP 8.4+.

## Usage

### Listening for connections

The `Server` class listens for incoming Xdebug connections and returns a `Session`:

```php
use DbgpClient\Server;

$server = new Server('127.0.0.1', 9003);
$server->listen();

// Block until Xdebug connects (30s timeout)
$session = $server->accept(30.0);

echo "Engine: {$session->init->engine->name} {$session->init->engine->version}\n";
echo "File: {$session->init->fileUri}\n";
```

Then trigger a PHP script with Xdebug enabled:

```bash
php -d xdebug.mode=debug -d xdebug.start_with_request=yes -d xdebug.client_port=9003 your_script.php
```

### Breakpoints

```php
use DbgpClient\Type\BreakpointType;

// Line breakpoint
$bp = $session->breakpointSet(BreakpointType::Line, 'file:///path/to/script.php', 42);
echo "Breakpoint ID: {$bp->id}\n";

// Conditional breakpoint
$session->breakpointSet(BreakpointType::Conditional, expression: '$count > 10');

// Remove a breakpoint
$session->breakpointRemove($bp->id);

// List all breakpoints
$list = $session->breakpointList();
```

### Execution control

```php
// Run until breakpoint or end
$response = $session->run();
echo "Status: {$response->status->value}\n"; // "break" or "stopping"

// Stepping
$session->stepOver();
$session->stepInto();
$session->stepOut();

// Stop execution
$session->stop();
```

### Inspecting state

```php
// Stack trace
$stack = $session->stackGet();
foreach ($stack->stack as $frame) {
    echo "#{$frame->level} {$frame->filename}:{$frame->lineno}";
    if ($frame->where) {
        echo " in {$frame->where}";
    }
    echo "\n";
}

// Local variables
$context = $session->contextGet();
foreach ($context->properties as $prop) {
    echo "{$prop->name} ({$prop->type}) = {$prop->value}\n";
}

// Inspect a specific variable (with nested expansion)
$prop = $session->propertyGet('$myArray', maxDepth: 2);

// Evaluate an expression
$result = $session->eval('$x + $y');
echo "Result: {$result->properties[0]->value}\n";
```

### Async commands

For event-loop integration, use `sendCommandAsync` to send execution commands without blocking:

```php
// Send run command, returns immediately with a transaction ID
$txId = $session->sendCommandAsync('run');

// ... do other work, use stream_select() on the connection ...

// Read the response when data is available
$response = $session->readResponse();
```

### Raw commands

For DBGp commands not covered by the convenience methods:

```php
$response = $session->sendCommand('feature_get', ['-n' => 'max_depth']);
echo $response->rawXml->asXML();
```

## License

MIT
