<?php

declare(strict_types=1);

// CLASS UnifiNetwork
class UnifiNetwork extends IPSModule
{
    /**
     * In contrast to Construct, this function is called only once when creating the instance and starting IP-Symcon.
     * Therefore, status variables and module properties which the module requires permanently should be created here.
     */
    public function Create()
    {
        //Never delete this line!
        parent::Create();

        $this->ConnectParent('{A792D3EC-FEC5-A8E6-F792-E141097C6AB0}');
        $this->RegisterPropertyString( 'ID', '' );
        $this->RegisterPropertyInteger( 'Timer', 0 );
		$this->RegisterTimer( 'Collect Data', 0, "UNIFINW_Send(\$_IPS['TARGET'],'getNetworkDetails','');" );
    }


    public function Destroy()
    {
        parent::Destroy();
    }

    public function Send(string $api, string $param1)
		{
			if ($this->HasActiveParent()) {
				$data=$this->SendDataToParent(json_encode(['DataID' => '{4A5538F1-1C38-198A-3144-D806E0DADF87}',
					'Api' => $api,
					'InstanceID' => $this->InstanceID,
					'Param1' => $param1
					]));
				if (!$data) {
					$this->SendDebug("UnifiNW", "Send Data Error: " . $api, 0);
					return;
				};
				switch($api) {
                    case "getNetworks":
						$siteData=json_encode(unserialize($data));
						$this->UpdateFormField("ID", "options", $siteData);
						$this->SetBuffer("Networks", $siteData);
                        $this->SendDebug("UnifiNW", "Get Network Data: " . $siteData, 0);
						break;
                    case "getNetworkDetails":                       
                       $JSONData=unserialize($data);
						if ( is_array( $JSONData ) && isset( $JSONData ) ) {
							if (isset($JSONData['code']) && $JSONData['code']==600) {
								// UniFi Network Version zu alt. Erst ab 10+ verfügbar
								$this->SetStatus( 600 );
								return;
							} else {
								$this->SetStatus( 102 );
							}
							$this->SendDebug("UnifiNW", json_encode($JSONData), 0);
                            $type = (isset($JSONData[ 'management' ]) ? $JSONData[ 'management' ] : 'UNMANAGED');
							$this->SetValue( 'NetworkName', (( isset($JSONData[ 'name' ]) ) ? $JSONData[ 'name' ] : 'Default') );
							$this->SetValue( 'NetworkEnabled',  (( isset($JSONData[ 'enabled' ]) ) ? $JSONData[ 'enabled' ] : false)  );
							$this->SetValue( 'NetworkType', $type );   
                            $this->SetValue( 'NetworkVlan', (( isset($JSONData[ 'vlanId']) ) ? $JSONData[ 'vlanId'] : 0) );
                            $this->SetValue( 'NetworkTrustedDHCP', (( isset($JSONData[ 'dhcpGuarding']['trustedDhcpServerIpAddresses'] ) ) ? $JSONData[ 'dhcpGuarding']['trustedDhcpServerIpAddresses'] : false) );
                            if ($type=="GATEWAY") {
                                $this->MaintainVariable('HostIpAddress',$this->Translate('Host IP Adresse'),3,['ICON' => 'network-wired','DECIMAL_SEPARATOR' => 'Client','COLOR' => -1,'MIN' => 0,'DIGITS' => 2,'MAX' => 100,'PRESENTATION' => '{3319437D-7CDE-699D-750A-3C6A3841FA75}','INTERVALS' => '[]','INTERVALS_ACTIVE' => false,'MULTILINE' => false,'OPTIONS' => '[]','PERCENTAGE' => false,'PREFIX' => '','SUFFIX' => '','THOUSANDS_SEPARATOR' => '','USAGE_TYPE' => 0],150,1);
                                $this->SetValue( 'HostIpAddress', (isset($JSONData[ 'ipv4Configuration' ]['hostIpAddress']) ? $JSONData[ 'ipv4Configuration' ]['hostIpAddress'] : '') );
                                $this->MaintainVariable('AutoScaleNetwork',$this->Translate('Auto scale network'),0,['ICON' => 'shield-halved','DECIMAL_SEPARATOR' => 'Client','COLOR' => -1,'MIN' => 0,'DIGITS' => 0,'MAX' => 100,'PRESENTATION' => '{3319437D-7CDE-699D-750A-3C6A3841FA75}','OPTIONS' => '[{"ColorDisplay":16077123,"Value":false,"Caption":"Inaktiv","IconValue":"wifi-slash","IconActive":true,"ColorActive":true,"ColorValue":16077123,"Color":-1},{"ColorDisplay":1692672,"Value":true,"Caption":"Aktiv","IconValue":"","IconActive":false,"ColorActive":true,"ColorValue":1692672,"Color":-1}]','INTERVALS_ACTIVE' => false,'MULTILINE' => false,'PERCENTAGE' => false,'PREFIX' => '','SUFFIX' => '','THOUSANDS_SEPARATOR' => '','USAGE_TYPE' => 0],151,1);
                                $this->SetValue( 'AutoScaleNetwork', (isset($JSONData[ 'ipv4Configuration' ]['autoScaleEnabled']) ? $JSONData[ 'ipv4Configuration' ]['autoScaleEnabled'] : false) );

                                
                                $mode = (isset($JSONData[ 'ipv4Configuration' ]['dhcpConfiguration']['mode']) ? $JSONData[ 'ipv4Configuration' ]['dhcpConfiguration']['mode'] : '');
                                $this->MaintainVariable('NetworkMode',$this->Translate('Mode'),3,['ICON' => 'network-wired','DECIMAL_SEPARATOR' => 'Client','COLOR' => -1,'MIN' => 0,'DIGITS' => 2,'MAX' => 100,'PRESENTATION' => '{3319437D-7CDE-699D-750A-3C6A3841FA75}','INTERVALS' => '[]','INTERVALS_ACTIVE' => false,'MULTILINE' => false,'OPTIONS' => '[]','PERCENTAGE' => false,'PREFIX' => '','SUFFIX' => '','THOUSANDS_SEPARATOR' => '','USAGE_TYPE' => 0],155,1);
                                if ($mode=="SERVER")
                                {
                                    $this->SetValue( 'NetworkMode', 'DHCP Server' );
                                    
                                } else {
                                    $this->SetValue( 'NetworkMode', 'DHCP Relay' );
                                }
                            }         
                        }
                        break;
                    }
			}
		}

    public function GetConfigurationForm()
    {
       		if ($this->HasActiveParent()) {
				$this->Send('getNetworks','');
			}	
			$arrayStatus = array();
			$arrayStatus[] = array( 'code' => 102, 'icon' => 'active', 'caption' => 'Instanz ist aktiv' );
			$arrayStatus[] = array( 'code' => 600, 'icon' => 'inactive', 'caption' => 'UniFi Network Version zu alt. Erst ab 10+ verfügbar' );
			
			$arrayElements = array();
			$arrayElements[] = array( 'type' => 'Label', 'bold' => true, 'label' => $this->Translate('UniFi Network Controller'));
			$arrayElements[] = array( 'type' => 'Label', 'label' => $this->Translate('Set Timer to activate instance. The instance will then automatically collect data from the device at the specified interval.')); 
			$arrayElements[] = array( 'type' => 'NumberSpinner', 'name' => 'Timer', 'caption' => 'Timer (s) -> 0=Off' );

			$Bufferdata = $this->GetBuffer("Networks");
			if ($Bufferdata=="") {
				$arrayOptions[] = array( 'caption' => 'Test', 'value' => '' );
			} else {
				$arrayOptions=json_decode($Bufferdata);
			}
			$arrayElements[] = array( 'type' => 'Select', 'name' => 'ID', 'caption' => 'Network ID', 'options' => $arrayOptions );
			
			$arrayActions = array();
			unset($arrayOptions);
			$arrayOptions[] = array( 'type' => 'Button', 'label' => $this->Translate('Get Networks'),'width' => '220px', 'onClick' => 'UNIFINW_Send($id,"getNetworks","");' );
			$arrayOptions[] = array( 'type' => 'Button', 'label' => $this->Translate('Get Network Details'),'width' => '220px', 'onClick' => 'UNIFINW_Send($id,"getNetworkDetails","");' );
			$arrayActions[] = array( 'type' => 'RowLayout',  'items' => $arrayOptions );
			return JSON_encode( array( 'status' => $arrayStatus, 'elements' => $arrayElements, 'actions' => $arrayActions ) );
	}


    public function ApplyChanges()
    {
       parent::ApplyChanges();
        $vpos = 100;
        $this->MaintainVariable( 'NetworkName', $this->Translate( 'Network Name' ), 3, [ 'PRESENTATION' => VARIABLE_PRESENTATION_VALUE_PRESENTATION, 'USAGE_TYPE'=> 0 ,'ICON'=> 'circle-info'], $vpos++, 1 );
		$this->MaintainVariable(
			'NetworkType',
			$this->Translate('Type'),
			3,
			[
				'ICON' => 'network-wired',
				'DECIMAL_SEPARATOR' => 'Client',
				'COLOR' => -1,
				'CONTENT_COLOR' => -1,
				'DIGITS' => 2,
				'MIN' => 0,				
				'MAX' => 100,
				'PRESENTATION' => '{3319437D-7CDE-699D-750A-3C6A3841FA75}',
				'INTERVALS' => '[]',
				'INTERVALS_ACTIVE' => true,
                'OPTIONS' => '[{"Value":"UNMANAGED","Caption":"Unmanaged","IconActive":false,"IconValue":"","ColorActive":false,"ColorValue":-1,"Color":-1,"ColorDisplay":-1},{"Value":"GATEWAY","Caption":"Gateway","IconActive":false,"IconValue":"","ColorActive":false,"ColorValue":-1,"Color":-1,"ColorDisplay":-1},{"Value":"SWITCH","Caption":"Switch","IconActive":false,"IconValue":"","ColorActive":false,"ColorValue":-1,"Color":-1,"ColorDisplay":-1}]',
				'MULTILINE' => false,
				'PERCENTAGE' => false,
				'PREFIX' => '',
				'SUFFIX' => '',
				'THOUSANDS_SEPARATOR' => '',
				'USAGE_TYPE' => 0
			],
			$vpos++,
			1
		);
		$this->MaintainVariable(
			'NetworkEnabled',
			$this->Translate('Enabled'),
			0,
			[
				'ICON' => 'network-wired',
				'DECIMAL_SEPARATOR' => 'Client',
				'COLOR' => -1,
				'MIN' => 0,
				'DIGITS' => 0,
				'MAX' => 100,
				'PRESENTATION' => '{3319437D-7CDE-699D-750A-3C6A3841FA75}',
				'OPTIONS' => '[{"ColorDisplay":16077123,"Value":false,"Caption":"Inaktiv","IconValue":"cloud-slash","IconActive":true,"ColorActive":true,"ColorValue":16077123,"Color":-1},{"ColorDisplay":1692672,"Value":true,"Caption":"Aktiv","IconValue":"","IconActive":false,"ColorActive":true,"ColorValue":1692672,"Color":-1}]',
				'INTERVALS_ACTIVE' => false,
				'MULTILINE' => false,
				'PERCENTAGE' => false,
				'PREFIX' => '',
				'SUFFIX' => '',
				'THOUSANDS_SEPARATOR' => '',
				'USAGE_TYPE' => 0
			],
			$vpos++,
			1
		);
		//$this->EnableAction('NetworkEnabled');
		$this->MaintainVariable(
			'NetworkVlan',
			$this->Translate('VLAN'),
			1,
			[
				'ICON' => 'diagram-project',
				'DECIMAL_SEPARATOR' => '',
				'COLOR' => -1,
				'MIN' => 0,
				'DIGITS' => 0,
				'MAX' => 6,
				'PRESENTATION' => '{3319437D-7CDE-699D-750A-3C6A3841FA75}',
				'OPTIONS' => '',
				'INTERVALS_ACTIVE' => false,
				'MULTILINE' => false,
				'PERCENTAGE' => false,
				'PREFIX' => '',
				'SUFFIX' => '',
				'THOUSANDS_SEPARATOR' => '',
				'USAGE_TYPE' => 0
			],
			$vpos++,
			1
		);
        $this->MaintainVariable(
			'NetworkTrustedDHCP',
			$this->Translate('Trusted DHCP'),
			3,
            [
				'ICON' => 'shield-halved',
				'DECIMAL_SEPARATOR' => 'Client',
				'COLOR' => -1,
				'MIN' => 0,
				'DIGITS' => 2,
				'MAX' => 100,
				'PRESENTATION' => '{3319437D-7CDE-699D-750A-3C6A3841FA75}',
				'INTERVALS' => '[]',
				'INTERVALS_ACTIVE' => false,
				'MULTILINE' => false,
				'OPTIONS' => '[]',
				'PERCENTAGE' => false,
				'PREFIX' => '',
				'SUFFIX' => '',
				'THOUSANDS_SEPARATOR' => '',
				'USAGE_TYPE' => 0
			],
			$vpos++,
			1
		);
        // Set status
		$TimerMS = $this->ReadPropertyInteger( 'Timer' ) * 1000;
		$this->SetTimerInterval( 'Collect Data', $TimerMS );
		if ( 0 == $TimerMS )
		{
			// instance inactive
			$this->SetStatus( 104 );
		} else {
			// instance active
			$this->SetStatus( 102 );
			$this->Send('getNetworkDetails','');
		}
    }


    
    public function RequestAction($ident, $value)
    {
        // Debug output
        $this->SendDebug(__FUNCTION__, $ident . ' => ' . $value, 0);
        // TODO: Replace identifier
        switch ($ident) {
            case 'NetworkEnabledXXX':
				//Gibt nur fehler zurük das VLAN ID ungültig sei oder IP Konfiguration ungültig...
                break;
            default:
                $this->SendDebug(__FUNCTION__, 'There was no reaction to the action.', 0);
        }
        return;
    }

}