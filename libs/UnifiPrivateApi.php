<?php

declare(strict_types=1);

/**
 * Unofficial (classic) UniFi Network API for the gateway.
 *
 * The official Integration API cannot switch ports, set PoE or block clients. These functions use
 * the classic API of the Network application with a local UniFi user (without MFA):
 * login at /api/auth/login, then /proxy/network/api/s/{site}/... with the cookie and the CSRF token.
 * Requests and fields follow the established implementation of aiounifi (Home Assistant).
 *
 * Password, cookie and CSRF token are credentials and never appear in the debug output.
 */
trait UnifiPrivateApi
{
    /** Bits of poe_caps: 4 = passive 24 V, 8 = passthrough (3 = PoE/PoE+, 35 = PoE++). */
    private const POE_CAP_PASV24 = 4;
    private const POE_CAP_PASSTHROUGH = 8;
    /** Network/VLAN settings of a port override that the console clears when the port is disabled. */
    private const PORT_BACKUP_FIELDS = [ 'forward', 'native_networkconf_id', 'tagged_vlan_mgmt', 'excluded_networkconf_ids', 'voice_networkconf_id', 'setting_preference' ];

    public function TestUnofficialAPI(): string
    {
        if ( !$this->privApi_enabled() ) {
            return $this->Translate( 'The unofficial API is not enabled or user/password are missing.' );
        }
        $this->WriteAttributeString( 'localToken', '' );
        if ( $this->privApi_login() === false ) {
            return $this->Translate( 'Login failed. Please check user and password (local UniFi user without MFA).' );
        }
        $devices = $this->privApi_request( '/stat/device', 'GET' );
        if ( $devices === null ) {
            return $this->Translate( 'Login successful, but the data request failed. Please check the site.' );
        }
        return sprintf( $this->Translate( 'Unofficial API works: %d devices found.' ), count( $devices ) );
    }

    private function privApi_enabled(): bool
    {
        return $this->ReadPropertyBoolean( 'privApiActive' )
            && $this->ReadPropertyString( 'localUser' ) !== ''
            && $this->ReadPropertyString( 'localPassword' ) !== '';
    }

    /** Logs in and stores cookie and CSRF token. Returns the token or false. */
    private function privApi_login(): array|false
    {
        $this->WriteAttributeString( 'localToken', '' );
        if ( !$this->privApi_enabled() ) {
            return false;
        }
        $url = $this->getBaseUrl().'/api/auth/login';
        $this->SendDebug( 'UnifiGW', 'privApi_login: POST '.$url.', user='.$this->ReadPropertyString( 'localUser' ), 0 );

        $responseHeaders = [];
        $ch = curl_init( $url );
        curl_setopt_array( $ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode( [
                'username' => $this->ReadPropertyString( 'localUser' ),
                'password' => $this->ReadPropertyString( 'localPassword' ),
                'rememberMe' => true
            ], JSON_UNESCAPED_UNICODE ),
            CURLOPT_HTTPHEADER => [ 'Accept: application/json', 'Content-Type: application/json' ],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_CONNECTTIMEOUT => self::CONNECT_TIMEOUT,
            CURLOPT_TIMEOUT => self::REQUEST_TIMEOUT,
            CURLOPT_HEADERFUNCTION => function ( $ch, string $line ) use ( &$responseHeaders ): int {
                $parts = explode( ':', $line, 2 );
                if ( count( $parts ) === 2 ) {
                    $responseHeaders[ strtolower( trim( $parts[ 0 ] ) ) ][] = trim( $parts[ 1 ] );
                }
                return strlen( $line );
            }
        ] );
        $response = curl_exec( $ch );
        $httpCode = (int) curl_getinfo( $ch, CURLINFO_HTTP_CODE );
        $error = $response === false ? curl_error( $ch ) : '';
        curl_close( $ch );
        // Header and body contain the token: only the status is logged.
        $this->SendDebug( 'UnifiGW', 'privApi_login: http='.$httpCode.( $error !== '' ? ', curl error: '.$error : '' ), 0 );
        if ( $response === false || $httpCode !== 200 ) {
            return false;
        }

        $last = function ( string $name ) use ( &$responseHeaders ): string {
            $values = $responseHeaders[ $name ] ?? [];
            return $values === [] ? '' : (string) $values[ count( $values ) - 1 ];
        };
        $csrf = $last( 'x-csrf-token' );
        $cookie = '';
        foreach ( $responseHeaders[ 'set-cookie' ] ?? [] as $setCookie ) {
            $pair = trim( explode( ';', $setCookie, 2 )[ 0 ] );
            $name = trim( explode( '=', $pair, 2 )[ 0 ] );
            if ( $name === 'TOKEN' || $name === 'UOS_TOKEN' ) {
                $cookie = $pair;
            }
        }
        $expiration = (int) $last( 'x-token-expire-time' );
        // The header is in milliseconds; renew one minute early. Without the header: one hour.
        $expiration = $expiration > 0 ? intdiv( $expiration, 1000 ) - 60 : time() + 3600;
        $this->SendDebug( 'UnifiGW', 'privApi_login: cookie='.( $cookie !== '' ? 'ok' : 'missing' ).', csrf='.( $csrf !== '' ? 'ok' : 'missing' ), 0 );
        if ( $cookie === '' ) {
            return false;
        }
        $token = [ 'cookie' => $cookie, 'csrf' => $csrf, 'expiration' => $expiration ];
        $this->WriteAttributeString( 'localToken', json_encode( $token ) );
        return $token;
    }

    private function privApi_getToken(): array|false
    {
        $token = json_decode( $this->ReadAttributeString( 'localToken' ), true );
        if ( is_array( $token ) && ( $token[ 'cookie' ] ?? '' ) !== '' && ( $token[ 'expiration' ] ?? 0 ) > time() ) {
            return $token;
        }
        return $this->privApi_login();
    }

    /**
     * Request against /proxy/network/api/s/{site}{endpoint}. Returns the "data" list of the
     * response or null on error. A rejected token leads to one new login.
     */
    private function privApi_request( string $endpoint, string $method, ?array $body = null ): ?array
    {
        if ( !$this->privApi_enabled() ) {
            return null;
        }
        // Several children may request at the same time; only one login at a time.
        $semaphore = 'UnifiNetPrivApi_'.$this->InstanceID;
        if ( !IPS_SemaphoreEnter( $semaphore, 5000 ) ) {
            $this->SendDebug( 'UnifiGW', 'privApi_request: semaphore timeout', 0 );
            return null;
        }
        try {
            $site = rawurlencode( $this->ReadPropertyString( 'Site' ) );
            $url = $this->getBaseUrl().'/proxy/network/api/s/'.$site.$endpoint;
            for ( $attempt = 1; $attempt <= 2; $attempt++ ) {
                $token = $this->privApi_getToken();
                if ( $token === false ) {
                    return null;
                }
                $headers = [ 'Accept: application/json', 'Content-Type: application/json', 'Cookie: '.$token[ 'cookie' ] ];
                if ( $token[ 'csrf' ] !== '' ) {
                    $headers[] = 'X-CSRF-Token: '.$token[ 'csrf' ];
                }
                $ch = curl_init( $url );
                curl_setopt_array( $ch, [
                    CURLOPT_CUSTOMREQUEST => $method,
                    CURLOPT_HTTPHEADER => $headers,
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_SSL_VERIFYPEER => false,
                    CURLOPT_SSL_VERIFYHOST => false,
                    CURLOPT_CONNECTTIMEOUT => self::CONNECT_TIMEOUT,
                    CURLOPT_TIMEOUT => self::REQUEST_TIMEOUT
                ] );
                if ( $body !== null ) {
                    $payload = json_encode( $body, JSON_UNESCAPED_UNICODE );
                    curl_setopt( $ch, CURLOPT_POSTFIELDS, $payload );
                    $this->SendDebug( 'UnifiGW', 'privApi_request: '.$method.' '.$endpoint.' '.$payload, 0 );
                } else {
                    $this->SendDebug( 'UnifiGW', 'privApi_request: '.$method.' '.$endpoint, 0 );
                }
                $response = curl_exec( $ch );
                $httpCode = (int) curl_getinfo( $ch, CURLINFO_HTTP_CODE );
                $error = $response === false ? curl_error( $ch ) : '';
                curl_close( $ch );
                if ( $response === false ) {
                    $this->SendDebug( 'UnifiGW', 'privApi_request: curl error: '.$error, 0 );
                    return null;
                }
                if ( $httpCode === 401 || $httpCode === 403 ) {
                    $this->SendDebug( 'UnifiGW', 'privApi_request: http '.$httpCode.', new login', 0 );
                    $this->WriteAttributeString( 'localToken', '' );
                    continue;
                }
                $json = json_decode( (string) $response, true );
                if ( $httpCode !== 200 || !is_array( $json ) || ( $json[ 'meta' ][ 'rc' ] ?? '' ) !== 'ok' ) {
                    $this->SendDebug( 'UnifiGW', 'privApi_request: http '.$httpCode.', response: '.substr( (string) $response, 0, 500 ), 0 );
                    return null;
                }
                return is_array( $json[ 'data' ] ?? null ) ? $json[ 'data' ] : [];
            }
            return null;
        } finally {
            IPS_SemaphoreLeave( $semaphore );
        }
    }

    private function privApi_normalizeMac( string $mac ): string
    {
        return strtolower( trim( $mac ) );
    }

    /** Raw device data of the classic API (contains _id, port_table and port_overrides). */
    private function privApi_getRawDevice( string $mac ): ?array
    {
        $mac = $this->privApi_normalizeMac( $mac );
        if ( $mac === '' ) {
            return null;
        }
        $data = $this->privApi_request( '/stat/device/'.rawurlencode( $mac ), 'GET' );
        return isset( $data[ 0 ][ '_id' ] ) ? $data[ 0 ] : null;
    }

    /** Ports of a switch with admin state and PoE. Only displayed fields go to the child. */
    private function privGetDevicePorts( string $mac ): array
    {
        $device = $this->privApi_getRawDevice( $mac );
        if ( $device === null ) {
            return [ 'ok' => false ];
        }
        $this->SendDebug( 'UnifiGW', 'privGetDevicePorts: port_overrides='.json_encode( $device[ 'port_overrides' ] ?? [] ), 0 );
        // port_table.enable follows a change only after the switch was provisioned (it stayed
        // true after disabling); the override shows the configured state at once.
        $overrideEnabled = [];
        foreach ( $device[ 'port_overrides' ] ?? [] as $override ) {
            $blocked = ( $override[ 'port_security_enabled' ] ?? false ) && ( $override[ 'port_security_mac_address' ] ?? [] ) === [];
            $overrideEnabled[ (int) ( $override[ 'port_idx' ] ?? -1 ) ] = !$blocked && ( $override[ 'forward' ] ?? '' ) !== 'disabled';
        }
        $ports = [];
        foreach ( $device[ 'port_table' ] ?? [] as $port ) {
            if ( !isset( $port[ 'port_idx' ] ) ) {
                continue;
            }
            $caps = (int) ( $port[ 'poe_caps' ] ?? 0 );
            $ports[] = [
                'idx' => (int) $port[ 'port_idx' ],
                'name' => (string) ( $port[ 'name' ] ?? '' ),
                'enabled' => $overrideEnabled[ (int) $port[ 'port_idx' ] ] ?? (bool) ( $port[ 'enable' ] ?? true ),
                'up' => (bool) ( $port[ 'up' ] ?? false ),
                'uplink' => (bool) ( $port[ 'is_uplink' ] ?? false ),
                'poe' => (bool) ( $port[ 'port_poe' ] ?? false ),
                'poeMode' => (string) ( $port[ 'poe_mode' ] ?? '' ),
                'poePasv24' => ( $caps & self::POE_CAP_PASV24 ) !== 0,
                'poePassthrough' => ( $caps & self::POE_CAP_PASSTHROUGH ) !== 0
            ];
        }
        return [ 'ok' => true, 'ports' => $ports ];
    }

    /**
     * Writes one changed port override. The complete list is sent, therefore it is read fresh
     * from the controller directly before the change.
     */
    private function privApi_updatePortOverride( string $mac, int $portIdx, callable $change ): bool
    {
        $device = $this->privApi_getRawDevice( $mac );
        if ( $device === null ) {
            return false;
        }
        $overrides = $device[ 'port_overrides' ] ?? [];
        $index = null;
        foreach ( $overrides as $i => $override ) {
            if ( (int) ( $override[ 'port_idx' ] ?? -1 ) === $portIdx ) {
                $index = $i;
                break;
            }
        }
        if ( $index === null ) {
            $override = [ 'port_idx' => $portIdx ];
            foreach ( $device[ 'port_table' ] ?? [] as $port ) {
                if ( (int) ( $port[ 'port_idx' ] ?? -1 ) === $portIdx && isset( $port[ 'portconf_id' ] ) ) {
                    $override[ 'portconf_id' ] = $port[ 'portconf_id' ];
                }
            }
            $overrides[] = $override;
            $index = array_key_last( $overrides );
        }
        $changed = $change( $overrides[ $index ] );
        if ( $changed === null ) {
            return false;
        }
        $overrides[ $index ] = $changed;
        return $this->privApi_request( '/rest/device/'.rawurlencode( (string) $device[ '_id' ] ), 'PUT', [ 'port_overrides' => array_values( $overrides ) ] ) !== null;
    }

    private function privSetPortEnabled( string $mac, int $portIdx, bool $enabled ): bool
    {
        // The uplink port would cut the switch off the network.
        foreach ( $this->privGetDevicePorts( $mac )[ 'ports' ] ?? [] as $port ) {
            if ( $port[ 'idx' ] === $portIdx && $port[ 'uplink' ] && !$enabled ) {
                $this->SendDebug( 'UnifiGW', 'privSetPortEnabled: port '.$portIdx.' is the uplink and is not disabled', 0 );
                return false;
            }
        }
        // When a port is disabled the console clears its network/VLAN settings. They are saved
        // here and restored when the port is enabled again.
        $key = $this->privApi_normalizeMac( $mac ).'|'.$portIdx;
        $backups = json_decode( $this->ReadAttributeString( 'PortBackup' ), true );
        if ( !is_array( $backups ) ) {
            $backups = [];
        }
        $newBackup = null;
        $ok = $this->privApi_updatePortOverride( $mac, $portIdx, function ( array $override ) use ( $enabled, $key, $backups, &$newBackup ): ?array {
            if ( ( $override[ 'port_security_mac_address' ] ?? [] ) !== [] ) {
                // A MAC filter of the user must not be lost.
                $this->SendDebug( 'UnifiGW', 'privSetPortEnabled: port has a MAC filter, not changed', 0 );
                return null;
            }
            $isDisabled = ( $override[ 'port_security_enabled' ] ?? false ) || ( $override[ 'forward' ] ?? '' ) === 'disabled';
            if ( !$enabled ) {
                // Only a working configuration is saved; disabling twice keeps the first backup.
                if ( !$isDisabled ) {
                    $newBackup = array_intersect_key( $override, array_flip( self::PORT_BACKUP_FIELDS ) );
                }
                // Port security without allowed MACs blocks the port (same as aiounifi).
                $override[ 'port_security_enabled' ] = true;
                return $override;
            }
            $override[ 'port_security_enabled' ] = false;
            if ( isset( $backups[ $key ] ) ) {
                // Fields that were not set before are removed, so the profile applies again.
                foreach ( self::PORT_BACKUP_FIELDS as $field ) {
                    unset( $override[ $field ] );
                }
                $override = array_merge( $override, $backups[ $key ] );
                $this->SendDebug( 'UnifiGW', 'privSetPortEnabled: restore '.json_encode( $backups[ $key ] ), 0 );
            } elseif ( ( $override[ 'native_networkconf_id' ] ?? null ) === '' && ( $override[ 'tagged_vlan_mgmt' ] ?? '' ) === 'block_all' ) {
                // Disabled elsewhere, nothing saved: back to the defaults of the console.
                unset( $override[ 'excluded_networkconf_ids' ], $override[ 'forward' ], $override[ 'native_networkconf_id' ], $override[ 'voice_networkconf_id' ] );
                $override[ 'tagged_vlan_mgmt' ] = 'auto';
            }
            return $override;
        } );
        if ( $ok ) {
            if ( $enabled ) {
                unset( $backups[ $key ] );
            } elseif ( $newBackup !== null ) {
                $backups[ $key ] = $newBackup;
                $this->SendDebug( 'UnifiGW', 'privSetPortEnabled: saved '.json_encode( $newBackup ), 0 );
            }
            $this->WriteAttributeString( 'PortBackup', json_encode( $backups ) );
        }
        return $ok;
    }

    private function privSetPoeMode( string $mac, int $portIdx, string $mode ): bool
    {
        if ( !in_array( $mode, [ 'auto', 'off', 'pasv24', 'passthrough' ], true ) ) {
            return false;
        }
        return $this->privApi_updatePortOverride( $mac, $portIdx, function ( array $override ) use ( $mode ): array {
            $override[ 'poe_mode' ] = $mode;
            return $override;
        } );
    }

    /** Blocked state (also for offline clients) and current throughput of a client. */
    private function privGetClient( string $mac ): array
    {
        $mac = $this->privApi_normalizeMac( $mac );
        if ( $mac === '' ) {
            return [ 'ok' => false ];
        }
        $user = $this->privApi_request( '/stat/user/'.rawurlencode( $mac ), 'GET' );
        if ( $user === null ) {
            return [ 'ok' => false ];
        }
        $result = [ 'ok' => true, 'blocked' => (bool) ( $user[ 0 ][ 'blocked' ] ?? false ), 'online' => false, 'tx' => 0.0, 'rx' => 0.0 ];
        $station = $this->privApi_request( '/stat/sta/'.rawurlencode( $mac ), 'GET' );
        if ( isset( $station[ 0 ] ) ) {
            $sta = $station[ 0 ];
            $wired = (bool) ( $sta[ 'is_wired' ] ?? false );
            $rates = array_filter( $sta, fn ( $key ) => str_contains( (string) $key, 'bytes-r' ), ARRAY_FILTER_USE_KEY );
            $this->SendDebug( 'UnifiGW', 'privGetClient: wired='.( $wired ? 'yes' : 'no' ).', rates='.json_encode( $rates ), 0 );
            $result[ 'online' ] = true;
            // Bytes per second from the network's point of view: tx = to the client, rx = from the client.
            $result[ 'tx' ] = (float) ( $wired ? ( $sta[ 'wired-tx_bytes-r' ] ?? $sta[ 'tx_bytes-r' ] ?? 0 ) : ( $sta[ 'tx_bytes-r' ] ?? 0 ) );
            $result[ 'rx' ] = (float) ( $wired ? ( $sta[ 'wired-rx_bytes-r' ] ?? $sta[ 'rx_bytes-r' ] ?? 0 ) : ( $sta[ 'rx_bytes-r' ] ?? 0 ) );
        }
        return $result;
    }

    private function privSetClientBlocked( string $mac, bool $blocked ): bool
    {
        $mac = $this->privApi_normalizeMac( $mac );
        if ( $mac === '' ) {
            return false;
        }
        return $this->privApi_request( '/cmd/stamgr', 'POST', [ 'cmd' => $blocked ? 'block-sta' : 'unblock-sta', 'mac' => $mac ] ) !== null;
    }

    /** Requests of the children for the unofficial API. Param1 is JSON. */
    private function privForward( string $api, string $param ): ?string
    {
        $p = json_decode( $param, true );
        if ( !is_array( $p ) ) {
            $p = [];
        }
        switch ( $api ) {
            case 'privAvailable':
                return serialize( $this->privApi_enabled() );
            case 'privGetDevicePorts':
                return serialize( json_encode( $this->privApi_enabled() ? $this->privGetDevicePorts( (string) ( $p[ 'mac' ] ?? '' ) ) : [ 'ok' => false ] ) );
            case 'privSetPortEnabled':
                return serialize( $this->privSetPortEnabled( (string) ( $p[ 'mac' ] ?? '' ), (int) ( $p[ 'port' ] ?? 0 ), (bool) ( $p[ 'enabled' ] ?? true ) ) );
            case 'privSetPoeMode':
                return serialize( $this->privSetPoeMode( (string) ( $p[ 'mac' ] ?? '' ), (int) ( $p[ 'port' ] ?? 0 ), (string) ( $p[ 'mode' ] ?? '' ) ) );
            case 'privGetClient':
                return serialize( json_encode( $this->privApi_enabled() ? $this->privGetClient( (string) ( $p[ 'mac' ] ?? '' ) ) : [ 'ok' => false ] ) );
            case 'privSetClientBlocked':
                return serialize( $this->privSetClientBlocked( (string) ( $p[ 'mac' ] ?? '' ), (bool) ( $p[ 'blocked' ] ?? false ) ) );
        }
        return null;
    }
}
