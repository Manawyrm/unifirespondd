UnifiRespondd
=======

![Meshviewer Screenshot](https://screenshot.tbspace.de/pxhcsfuoijw.png)
 
UnifiRespondd is a small PHP script, that allows Freifunk installations utilizing the propritary Unifi firmware to be visible on the map.  
It uses SNMP to talk to the APs directly (no controller necessary) and listens to the IPv6 multicast socket.  
This script behaves exactly like regular routers and can be installed anywhere in the L2-mesh, without needing to modify the map itself.  

To use SNMP, SNMPv1/v2 need to be enabled and a community has to be set:  
![Unifi Settings](https://screenshot.tbspace.de/bswonfhtpdk.png)

### System requirements
php-cli and php-snmp
  
### Autostart
```
cp unifirespondd.php /opt/
cp unifirespondd.service /etc/systemd/system/ 
systemctl daemon-reload
systemctl enable --now unifirespondd
```

### Configuration
Configuration parameters can be found on the start of unifirespondd.php.  
SNMP community and the list of devices needs to be set. The multicast group can be left unchanged for most installations.  
OffloaderMAC is the MAC address of the device which provides uplink for the access points. The connection between offloader and AP is shown as a wired link on the map. 