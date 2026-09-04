<?php
declare(strict_types=1);

namespace Oshim\Cli\Commands;

use Oshim\Cli\Command;
use Oshim\Cli\Input;
use Oshim\Cli\Output;
use Oshim\WebRtc\WebRtcSignalingServer;
use Oshim\WebRtc\MediaRoomManager;

/**
 * CLI Command to boot the sovereign Async WebRTC Signaling & Real-Time Multimedia Server.
 */
class WebRtcServeCommand extends Command
{
    protected function configure(): void
    {
        $this->name = 'webrtc:serve';
        $this->description = 'Start the Async WebRTC Signaling & Real-Time Multimedia Server';
        $this->help = 'Run the non-blocking WebSocket signaling engine for peer-to-peer audio/video mesh and SFU room negotiation.';

        $this->addOption('host', 'H', Input::VALUE_REQUIRED, 'Host IP address to bind to', '0.0.0.0')
             ->addOption('port', 'p', Input::VALUE_REQUIRED, 'TCP port to listen on', '9090')
             ->addOption('topology', 't', Input::VALUE_REQUIRED, 'Default room media topology (mesh|sfu)', 'mesh')
             ->addOption('daemon', 'd', Input::VALUE_NONE, 'Run server in daemon background mode');
    }

    public function execute(Input $input, Output $output): int
    {
        $host = (string)$input->getOption('host', '0.0.0.0');
        $port = (int)$input->getOption('port', 9090);
        $topology = (string)$input->getOption('topology', 'mesh');
        $daemon = (bool)$input->getOption('daemon', false);

        $output->writeln("<info>👑 OSHIM Sovereign WebRTC Signaling Engine</info>");
        $output->writeln("  <comment>Host:</comment>      {$host}");
        $output->writeln("  <comment>Port:</comment>      {$port}");
        $output->writeln("  <comment>Topology:</comment>  {$topology}");
        $output->writeln("  <comment>Mode:</comment>      " . ($daemon ? 'Daemon Background' : 'Interactive'));
        $output->writeln("  <comment>Endpoint:</comment>  ws://{$host}:{$port}");
        $output->writeln("  <comment>Protocol:</comment>  RFC 6455 WebSocket / RFC 4566 SDP / Trickle ICE");
        $output->writeln("<info>✔ WebRTC Signaling Server listening on {$host}:{$port}</info>");

        $roomManager = new MediaRoomManager();
        $server = new WebRtcSignalingServer(null, $roomManager);
        $server->listen($host, $port);

        return 0;
    }
}
