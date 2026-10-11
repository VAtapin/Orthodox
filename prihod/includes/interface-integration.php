<?php
namespace Prihod;

defined( 'ABSPATH' ) || exit;

interface Integration {
    public function id(): string;

    /** Name, capabilities, settings_url, blocks, pages and external_services. */
    public function describe(): array;

    /** Local health information: status (ready, needs_setup, unavailable) and message. */
    public function health(): array;
}
