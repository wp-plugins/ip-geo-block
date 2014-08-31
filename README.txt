=== IP Geo Block ===
Contributors: tokkonopapa
Donate link:
Tags: comment, spam, IP address, geolocation
Requires at least: 3.5
Tested up to: 3.9.2
Stable tag: 1.1.0
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

A WordPress plugin that blocks any comments posted from outside your nation.

== Description ==

This plugin will examine a country code based on the posting author's IP 
address. If the comment comes from undesired country, it will be blocked 
before Akismet validate it.

Free IP Geolocation REST APIs are installed in this plugin to get a country 
code from an IP address. There are two types of API which support only IPv4 or 
both IPv4 and IPv6. This plugin will automatically select an appropriate API.

Starting with version 1.1.0, the cache mechanism with transient API for the 
fetched IP addresses has been equipped to reduce load on the server against 
spam comment.

= Using with IP2Location WordPress Plugins =

If you have correctly installed one of the IP2Location plugins (
    [IP2Location Tags][IP2Tag],
    [IP2Location Variables][IP2Var],
    [IP2Location Country Blocker][IP2Blk]
), this plugin uses its local database prior to the REST APIs.

After installing these IP2Location plugins, this plugin should be once 
deactivated and then activated in order to set the path to `database.bin` 
and `ip2location.class.php`.

= Development =

Development of this plugin is promoted on [GitHub][github]. All contributions 
will be welcome.

= Attribution =

Thanks for providing these great services for free.

* [http://freegeoip.net/][freegeoip]     (IPv4 / free)
* [http://ipinfo.io/][ipinfo]            (IPv4, IPv6 / free)
* [http://www.telize.com/][Telize]       (IPv4, IPv6 / free)
* [http://www.iptolatlng.com/][IP2LL]    (IPv4, IPv6 / free)
* [http://ip-json.rhcloud.com/][IPJson]  (IPv4, IPv6 / free)
* [http://xhanch.com/][Xhanch]           (IPv4 / free)
* [http://mshd.net/][mshdnet]            (IPv4, IPv6 / free)
* [http://www.geoplugin.com/][geoplugin] (IPv4, IPv6 / free, need an attribution link)
* [http://ip-api.com/][ipapi]            (IPv4, IPv6 / free for non-commercial use)
* [http://smart-ip.net/][smartip]        (IPv4, IPv6 / free for personal and non-commercial use)
* [http://ipinfodb.com/][IPInfoDB]       (IPv4, IPv6 / free for registered user, need API key)

Some of these services and APIs use GeoLite data created by [MaxMind][MaxMind],
and some include IP2Location LITE data available from [IP2Location][IP2Loc].

== Installation ==

= Using The WordPress Dashboard =

1. Navigate to the 'Add New' in the plugins dashboard
2. Search for 'IP Geo Block'
3. Click 'Install Now'
4. Activate the plugin on the Plugin dashboard

= Settings =

* **Service provider and API key**  
    If you want to use `IPInfoDB`, you should register from 
	[their site][IPInfoDB] to get a free API key and set it into the textfield.
	And `ip-api.com` and `Smart-IP.net` require non-commercial use.

* **Text position on comment form**  
    If you want to put some text message on your comment form, please select
    `Top` or `Bottom` and put text into the **Text message on comment form**
    textfield.

* **Matching rule**  
    Select `White list` (recommended) or `Black list` to specify the countries
    from which you want to pass or block.

* **White list**, **Black list**  
    Specify the country code with two letters (see [ISO 3166-1 alpha-2][ISO]).
	Each of them should be separated by comma.

* **Response code**  
    Select one of the [response code][RFC] to be sent when it blocks a comment.
	The 2xx code will refresh to your top page, the 3xx code will redirect to 
    [Black Hole Server][BHS], the 4xx code will lead to WordPress error page, 
	and the 5xx will pretend an error.

* **Remove settings at uninstallation**  
    If you checked this option, all settings will be removed when this plugin
    is uninstalled for clean uninstalling.

== Frequently Asked Questions ==

= What is this plugin for? =

It's for blocking spam comments. If you can not specify countries with white 
list or black list to protect your site against spam comments, you should 
choose other awesome plugins.

= How can I check this plugin works? =

Check `statistics` tab on this plugin's option page.

= How can I test on the local site? =

There are two ways. One is to add some code somewhere in your php 
(typically `functions.php` in your theme) to substitute local IP address 
through filter fook `ip-geo-block-addr` as follows:

`function substitute_my_ip( $ip ) {
    return '98.139.183.24'; // yahoo.com
}
add_filter( 'ip-geo-block-addr', 'substitute_my_ip' );`

Another method is adding a country code into `White list` or `Black list`.
Most of the IP Geolocation services return empty (with some status) if a local 
IP address (e.g. 127.0.0.0) is sent, but only `freegeoip.net` returns `RD`.

= Can I add an additional spam validation function into this plugin? =

Yes, you can use `add_filter()` with filter hook `ip-geo-block-validate` in 
somewhere (typically `functions.php` in your theme) as follows:

`function my_validation( $commentdata ) {
    // validation code here
    ...;

    if ( ... /* if validation fails */ ) {
        // tell the plugin this comment should be blocked!!
        $commentdata['ip-geo-block']['result'] = 'blocked';
    }

    return $commentdata;
}
add_filter( 'ip-geo-block-validate', 'my_validation' );`

Then you can find `ZZ` as a country code in the list of `Blocked by countries` 
on the `statistics` tab of this plugin's option page.

See [preprocess comment][codex] for more detail about `$commentdata`.

= Can I change user agent strings when fetching services? =

Yes. The default is something like `Wordpress/3.9.2; ip-geo-block 1.0.4`.
You can change it as follows:

`function my_user_agent( $args ) {
    $args['user-agent'] = 'original user agent strings';
    return $args;
}
add_filter( 'ip-geo-block-headers', 'my_user_agent' );`

== Other Notes ==

Before updating to 1.1.x from version 1.0.x, please deactivate then activate 
this plugin on the plugin dashboard.

If you do not want to keep the IP2Location plugins (
    [IP2Location Tags][IP2Tag],
    [IP2Location Variables][IP2Var],
    [IP2Location Country Blocker][IP2Blk]
) in `wp-content/plugins/` directory but just want to use its database, 
you can rename it to `ip2location` and upload it to `wp-content/`.

== Screenshots ==

1. **IP Geo Plugin** - Settings.
2. **IP Geo Plugin** - Statistics.
3. **IP Geo Plugin** - Search.
4. **IP Geo Plugin** - Attribution.

== Changelog ==

= 1.1.0 =
* Implement the cache mechanism to reduce load on the server.
* Better handling of errors on the search tab so as to facilitate the 
  analysis of the service problems.
* Fixed a bug of setting user agent strings in 1.0.2.
  Now the user agent strings (`WordPress/3.9.2; http://example.com/`) 
  becomes to its own (`WordPress/3.9.2; ip-geo-block 1.1.0`).

= 1.0.3 =
* Temporarily stop setting user agent strings to supress a bug in 1.0.2.

= 1.0.2 =
* Update provider settings. Smart-IP.net was terminated, ipinfo.io is now
  available for IPv6.
* Set the own user agent strings for `WP_Http`.

= 1.0.1 =
* Modify Plugin URL.
* Add `apply_filters()` to be able to change headers.

= 1.0.0 =
* Ready to release.

= 0.9.9 =
* Refine UI and modify settings data format.

= 0.9.8 =
* Add support for IP2Location WordPress plugins.

= 0.9.7 =
* Refine UI of provider selection and API key setting.
* Fix js error on setting page.

= 0.9.6 =
* Change all class names and file names.
* Simplify jQuery Google Map plugin.
* Add some providers.
* Add `ip-geo-block-addr` filter hook for local testing.
* Add `enables` to option table for the future usage.

= 0.9.5 =
* Fix garbage characters of `get_country()` for ipinfo.io.

= 0.9.4 =
* Add `ip-geo-block-validate` hook and `apply_filters()` in order to add
  another validation function.

= 0.9.3 =
* Change action hook `pre_comment_on_post` to `preprocess_comment`.
* Add attribution links to appreciate providing the services. 

= 0.9.2 =
* Add a check of the supported type of IP address not to waste a request.

= 0.9.1 =
* Delete functions for MU, test, debug and ugly comments.

= 0.9.0 =
* Pre-release version.

== Upgrade Notice ==

[freegeoip]:http://freegeoip.net/ "freegeoip.net: FREE IP Geolocation Web Service"
[ipinfo]:   http://ipinfo.io/ "ipinfo.io - ip address information including geolocation, hostname and network details"
[Telize]:   http://www.telize.com/ "Telize - JSON IP and GeoIP REST API"
[IP2LL]:    http://www.iptolatlng.com/ "IP to Latitude, Longitude"
[IPJson]:   http://ip-json.rhcloud.com/ "Free IP Geolocation Web Service"
[Xhanch]:   http://xhanch.com/xhanch-api-ip-get-detail/ "Xhanch API &#8211; IP Get Detail | Xhanch Studio"
[mshdnet]:  http://mshd.net/documentation/geoip "www.mshd.net - Geoip Documentation"
[geoplugin]:http://www.geoplugin.com/ "geoPlugin to geolocate your visitors"
[ipapi]:    http://ip-api.com/ "IP-API.com - Free Geolocation API"
[smartip]:  http://smart-ip.net/geoip-api "Geo-IP API Documentation"
[IPInfoDB]: http://ipinfodb.com/ "IPInfoDB | Free IP Address Geolocation Tools"
[MaxMind]:  http://www.maxmind.com "MaxMind - IP Geolocation and Online Fraud Prevention"
[IP2Loc]:   http://www.ip2location.com "IP Address Geolocation to Identify Website Visitor's Geographical Location"
[IP2Tag]:   http://wordpress.org/plugins/ip2location-tags/ "WordPress › IP2Location Tags « WordPress Plugins"
[IP2Var]:   http://wordpress.org/plugins/ip2location-variables/ "WordPress › IP2Location Tags « WordPress Plugins"
[IP2Blk]:   http://wordpress.org/plugins/ip2location-country-blocker/ "WordPress › IP2Location Country Blocker « WordPress Plugins"
[github]:   https://github.com/tokkonopapa/WordPress-IP-Geo-Block "tokkonopapa/WordPress-IP-Geo-Block · GitHub"
[codex]:    http://codex.wordpress.org/Plugin_API/Filter_Reference/preprocess_comment "Plugin API/Filter Reference/preprocess comment &laquo; WordPress Codex"
[BHS]:      http://blackhole.webpagetest.org/
[ISO]:      http://en.wikipedia.org/wiki/ISO_3166-1_alpha-2#Officially_assigned_code_elements "ISO 3166-1 alpha-2 - Wikipedia, the free encyclopedia"
[RFC]:      http://tools.ietf.org/html/rfc2616#section-10 "RFC 2616 - Hypertext Transfer Protocol -- HTTP/1.1"
