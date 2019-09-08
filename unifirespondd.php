<?php 
// snmpwalk -v2c -c observium 10.4.0.44 -m ALL 1.3.6.1.4.1.41112
$config = [
	"mcastGroup" => "ff05::2:1001",
	"snmpCommunity" => "observium",
	"contact" => "freifunk@dna-ev.de",
	"site_code" => "ffh",
	"domain_code" => "alfeld",

	"devices" => [
		[
			"name" => "Alfeld-Bahnhof-Cafe",
			"mac" => "78:8a:20:48:cc:9c",
			"model" => "Ubiquiti UniFi-AC-MESH",
			"latitude" => 51.981446, 
			"longitude" => 9.818439,
			"snmpIP" => "10.4.0.44",
			"offloaderMAC" => "00:19:99:e7:4b:74"
		],
		[
			"name" => "Alfeld-Bahnhof-Busbahnhof",
			"mac" => "80:2a:a8:c9:17:72",
			"model" => "Ubiquiti UniFi-AC-MESH",
			"latitude" => 51.981302,
			"longitude" => 9.818694,
			"snmpIP" => "10.4.0.43",
			"offloaderMAC" => "00:19:99:e7:4b:74"
		],
	],
];

const unifiVapNumStations 	= '.1.3.6.1.4.1.41112.1.6.1.2.1.8';
const unifiVapChannel 		= '.1.3.6.1.4.1.41112.1.6.1.2.1.4';
const unifiApSystemUptime   = '.1.3.6.1.4.1.41112.1.6.3.5.0';

const unifiIfRxBytes = '.1.3.6.1.4.1.41112.1.6.2.1.1.6.1';
const unifiIfRxPackets = '.1.3.6.1.4.1.41112.1.6.2.1.1.10.1';

const unifiIfTxBytes = '.1.3.6.1.4.1.41112.1.6.2.1.1.12.1';
const unifiIfTxPackets = '.1.3.6.1.4.1.41112.1.6.2.1.1.15.1';

mainLoop($config);

function mainLoop($config)
{
	$socket = socket_create(AF_INET6, SOCK_DGRAM, SOL_UDP);
	socket_set_option($socket, SOL_SOCKET, SO_REUSEADDR, 1);

	socket_set_option($socket, IPPROTO_IPV6, MCAST_JOIN_GROUP, [
		"group" => $config['mcastGroup'],
		"interface" => 0,
	]);

	if (!socket_bind($socket, '::', 1001))
		echo 'Unable to bind socket: '. socket_strerror(socket_last_error()) . PHP_EOL;

	$from = "";
	$port = 0;
	$sendbuf = "";
	while (true)
	{
		socket_recvfrom($socket, $buf, 1024, MSG_WAITALL, $from, $port);

		echo "[" . date("H:i:s") . " - " . $from . "] " . $buf . "\n"; 
		$data = [];
		$buf = trim($buf); 
		$buf = explode(" ", $buf);

		foreach ($config['devices'] as $deviceID => $device)
		{
			$snmpData = fetchSNMP($config, $device);
			if ($snmpData === false)
				continue; 

			if (strtoupper(trim($buf[0])) == "GET")
			{
				foreach ($buf as $i => $provider)
				{
					if ($i == 0) continue; 

					$provider = trim($provider); 
					if (!empty($provider))
					{
						$data[$provider] = getData($provider, $snmpData, $device, $config);

						if ($data[$provider] === false)
							unset($data[$provider]);
					}
				}

				$sendbuf = gzdeflate(json_encode($data));
			}
			else
			{
				$data = getData($buf[0], $snmpData, $device, $config);
				$sendbuf = json_encode($data);
			}
			socket_sendto($socket, $sendbuf, strlen($sendbuf), 0, $from, $port);
		}

	}
}

function fetchSNMP($config, $device)
{
	try 
	{
		snmp_set_quick_print(1);
		$session = new SNMP(SNMP::VERSION_1, $device['snmpIP'], $config['snmpCommunity'], 100000);
		$session->exceptions_enabled = SNMP::ERRNO_ANY;

		$clients24 = 0; 
		$clients5  = 0; 

		for ($i=1; $i < 10; $i++)
		{ 
			$numStations = $session->get(unifiVapNumStations . "." . $i);
			$channel = $session->get(unifiVapChannel . "." . $i);

			if ($channel < 30)
				$clients24 += $numStations; 
			else
				$clients5  += $numStations;
		}
		
		return [
			"uptime" => (int)$session->get(unifiApSystemUptime),

			"rxBytes" => (int)$session->get(unifiIfRxBytes),
			"rxPackets" => (int)$session->get(unifiIfRxPackets),
			"txBytes" => (int)$session->get(unifiIfTxBytes),
			"txPackets" => (int)$session->get(unifiIfTxPackets),

			"clients24" => (int)$clients24,
			"clients5"  => (int)$clients5,
		];
	}
	catch (Exception $e)
	{
		return false; 	
	}
}



function getData($provider = "nodeinfo", $snmp, $device, $config)
{
	if ($provider == "nodeinfo")
	{
		return [
		'software' => [
			'autoupdater' => [
				'wifi-fallback' => false,
				'branch' => '-',
				'enabled' => false,
			],
			'batman-adv' => [
				'version' => '',
				'compat' => 15,
			],
			'fastd' => [
				'version' => '',
				'enabled' => false,
			],
			'firmware' => [
				'base' => '',
				'release' => 'non-gluon - UniFi proprietary',
			],
		],
		'network' => [
			'addresses' => [
				"https://dna-ev.de"
			],
			/*'mesh' => [
				'bat0' => [
					'interfaces' => [
						'wireless' => [
						   'de:ad:c0:ff:ee:23',
						],
						'other' => [
							'de:ad:c0:ff:ee:42',
						],
					],
				],
			],*/
			'mac' => $device['mac'],
		],
		'location' => [
			'latitude' => $device['latitude'], 
			'longitude' => $device['longitude'],
		],
		'owner' => [
			'contact' => $config['contact'],
		],
		'system' => [
			'site_code' => $config['site_code'],
			'domain_code' => $config['domain_code'],
		],			  
		'node_id' => str_replace(":", "", $device['mac']),
		'hostname' => $device['name'],
		'hardware' => [
			'model' => $device['model'],
			'nproc' => 1,
		],
		];
	}
	if ($provider == "statistics")
	{
		return [
				'wireless' => [
				],
				'clients' => [
					'total' => $snmp['clients24'] + $snmp['clients5'],
					'wifi' => 0,
					'wifi24' => $snmp['clients24'],
					'wifi5' => $snmp['clients5'],
				],
				'traffic' => [
					'rx' => [
						'packets' => $snmp['rxPackets'],
						'bytes' => $snmp['rxBytes'],
					],
					'tx' => [
						'packets' => $snmp['txPackets'],
						'bytes' => $snmp['txBytes'],
						'dropped' => 0,
					],
					'forward' => [
						'packets' => 0,
						'bytes' => 0,
					],
					'mgmt_rx' => [
						'packets' => 0,
						'bytes' => 0,
					],
					'mgmt_tx' => [
						'packets' => 0,
						'bytes' => 0,
					],
				],
				
				'node_id' => str_replace(":", "", $device['mac']),
				'time' => time(),
				'uptime' => $snmp['uptime'],
			];
	}
	if ($provider == "neighbours")
	{
		return [
			'batadv' => [
				$device['mac'] => [
					'neighbours' => [
						$device['offloaderMAC'] => [
							'tq' => 255,
							'lastseen' => 0,
						],
					],
				],
			],
			'wifi' => [
			],
			'node_id' => str_replace(":", "", $device['mac']),
		];
	}
	return false; 
}