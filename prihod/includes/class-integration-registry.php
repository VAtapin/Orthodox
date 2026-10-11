<?php
namespace Prihod;

defined( 'ABSPATH' ) || exit;

final class Integration_Registry {
    private array $integrations = array();

    public function register( Integration $integration ): void {
        $id = $integration->id();
        if ( ! preg_match( '/^[a-z][a-z0-9_-]{0,63}$/D', $id ) ) {
            throw new \InvalidArgumentException( 'Invalid Prihod integration ID.' );
        }
        if ( isset( $this->integrations[ $id ] ) ) {
            throw new \LogicException( 'Prihod integration is already registered: ' . $id );
        }
        $this->integrations[ $id ] = $integration;
    }

    public function all(): array {
        return $this->integrations;
    }

    public function get( string $id ): ?Integration {
        return $this->integrations[ $id ] ?? null;
    }

    public function report( Integration $integration ): array {
        try {
            $description = $integration->describe();
            $health = $integration->health();
            if ( ! isset( $description['name'], $health['status'], $health['message'] )
                || ! is_string( $description['name'] ) || ! is_string( $health['message'] )
                || ! in_array( $health['status'], array( 'ready', 'needs_setup', 'unavailable' ), true ) ) {
                throw new \UnexpectedValueException( 'Invalid integration report.' );
            }
            return array( 'name' => $description['name'], 'status' => $health['status'], 'message' => $health['message'] );
        } catch ( \Throwable $error ) {
            // Do not expose credentials or external responses from exception messages.
            return array(
                'name' => $integration->id(), 'status' => 'unavailable',
                'message' => __( 'Не удалось проверить подключение.', 'prihod' ),
            );
        }
    }
}
