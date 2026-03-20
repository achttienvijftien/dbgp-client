<?php

declare(strict_types=1);

namespace DbgpClient\Response;

use DbgpClient\Exception\ProtocolException;
use DbgpClient\Type\EngineInfo;

final readonly class InitResponse
{
    public function __construct(
        public string $appId,
        public string $ideKey,
        public string $session,
        public string $thread,
        public string $parent,
        public string $language,
        public string $protocolVersion,
        public string $fileUri,
        public EngineInfo $engine,
        /**
         * Xdebug 3.5+ out-of-band control socket address, e.g.
         * "unix:///tmp/xdebug.ctrl.12345" or "\\.\pipe\xdebug-ctrl.*".
         * Null on older Xdebug versions that do not advertise the attribute.
         */
        public ?string $ctrlSocket = null,
    ) {
    }

    public static function fromXml(string $xml): self
    {
        libxml_use_internal_errors(true);
        $doc = simplexml_load_string($xml);

        if ($doc === false) {
            $errors = libxml_get_errors();
            libxml_clear_errors();
            $message = !empty($errors) ? $errors[0]->message : 'Unknown XML error';
            throw new ProtocolException("Failed to parse init XML: {$message}");
        }

        if ($doc->getName() !== 'init') {
            throw new ProtocolException("Expected init packet, got: {$doc->getName()}");
        }

        $engineName = isset($doc->engine) ? (string) $doc->engine : '';
        $engineVersion = isset($doc->engine['version']) ? (string) $doc->engine['version'] : null;

        $xdebugNs   = $doc->attributes('https://xdebug.org/dbgp/xdebug');
        $ctrlSocket = ($xdebugNs !== null && isset($xdebugNs['ctrl-socket']))
            ? (string) $xdebugNs['ctrl-socket']
            : null;

        return new self(
            appId: (string) ($doc['appid'] ?? ''),
            ideKey: (string) ($doc['idekey'] ?? ''),
            session: (string) ($doc['session'] ?? ''),
            thread: (string) ($doc['thread'] ?? ''),
            parent: (string) ($doc['parent'] ?? ''),
            language: (string) ($doc['language'] ?? ''),
            protocolVersion: (string) ($doc['protocol_version'] ?? ''),
            fileUri: (string) ($doc['fileuri'] ?? ''),
            engine: new EngineInfo($engineName, $engineVersion),
            ctrlSocket: $ctrlSocket,
        );
    }
}
