=== IP Geo Block ===
Contributors: tokkonopapa
Donate link:
Tags: comment, spam, IP address, geolocation
Requires at least: 3.5
Tested up to: 3.8.1
Stable tag: 1.0.1
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

A WordPress plugin that blocks any comments posted from outside your nation.

== Description ==

This plugin will block any comments posted from outside the specified countries.

In order to check the county of the posting author by IP address, this plugin 
uses the IP address Geolocation REST APIs.

Some of these services and APIs include GeoLite data created by 
[MaxMind](http://www.maxmind.com "MaxMind - IP Geolocation and Online Fraud Prevention"),
and some include IP2Location LITE data available from 
[IP2Location](http://www.ip2location.com "IP Address Geolocation to Identify Website Visitor's Geographical Location").

If you have installed one of the IP2Location plugin (
[IP2Location Tags](http://wordpress.org/plugins/ip2location-tags/ "WordPress › IP2Location Tags « WordPress Plugins"),
[IP2Location Variables](http://wordpress.org/plugins/ip2location-variables/ "WordPress › IP2Location Tags « WordPress Plugins"),
[IP2Location Country Blocker](http://wordpress.org/plugins/ip2location-country-blocker/ "WordPress › IP2Location Country Blocker « WordPress Plugins")
) correctly, this plugin uses it instead of REST APIs.

== Installation ==

= Using The WordPress Dashboard =

1. Navigate to the 'Add New' in the plugins dashboard
2. Search for 'IP Geo Block'
3. Click 'Install Now'
4. Activate the plugin on the Plugin dashboard

== Frequently Asked Questions ==

= What is this plugin for? =

It's for blocking spam comments. If you can not specify countries with white 
list or black list to protect your site against spam comments, you should 
choose other awesome plugins.

= How can I check this plugin works? =

Check `statistics` tab on this plugin's option page.

= How can I test on the local site? =

There are two ways. One is to add some code somewhere in your php (typically 
`functions.php` in your theme) to substitute local IP address through filter
fook `ip-geo-block-addr` as follows:

`function substitute_my_ip( $ip ) {
    return '98.139.183.24'; // yahoo.com
}
add_filter( 'ip-geo-block-addr', 'substitute_my_ip' );`

And another is adding `RD` as a country code into `White list` or `Black list`.
Most of the IP Geolocation services return empty (with some status) if a local 
IP address (e.g. 127.0.0.0) is sent, but only `freegeoip.net` returns `RD`.

= Can I add an additional spam validation function into this plugin? =

Yes, you can use `add_filter()` with filter hook `ip-geo-block-validate` in 
somewhere (typically `functions.php` in your theme) as follows:

`function your_validation( $commentdata ) {
    // your validation code here
    ...;

    if ( ... /* if your validation fails */ ) {
        // tell the plugin this comment should be blocked!!
        $commentdata['ip-geo-block']['result'] = 'blocked';
    }

    return $commentdata;
}
add_filter( 'ip-geo-block-validate', 'your_validation' );`

Then you can find `ZZ` as a country code in the list of `Blocked by countries` 
on the `statistics` tab of this plugin's option page.

== Other Notes ==

= Settings on Dashboard =

* **Service provider and API key**  
    If you want to use `IPInfoDB`, you should register from 
    [their site](http://ipinfodb.com/ "IPInfoDB | Free IP Address Geolocation Tools")
    to get a free API key and set it into the textfield. And `ip-api.com` and 
    `Smart-IP.net` require non-commercial use. To use these APIs, you should 
    put a word (anything you like) into the textfield.

* **Text position on comment form**  
    If you want to put some text message on your comment form, please select
    `Top` or `Bottom` and put text into the **Text message on comment form**
    textfield.

* **Matching rule**  
    Select `White list` (recommended) or `Black list` to specify the countries
    from which you want to pass or block.

* **White list**, **Black list**  
    Specify the country code with two letters (see 
    [ISO 3166-1 alpha-2](http://en.wikipedia.org/wiki/ISO_3166-1_alpha-2#Officially_assigned_code_elements "ISO 3166-1 alpha-2 - Wikipedia, the free encyclopedia")
    ). Each of them should be separated by comma.

* **Response code**  
    Select one of the 
    [response code](http://tools.ietf.org/html/rfc2616#section-10 "RFC 2616 - Hypertext Transfer Protocol -- HTTP/1.1")
    to to be sent when it blocks a comment. The 2xx code will refresh to your 
    top page, the 3xx code will redirect to 
    [Black Hole Server](http://blackhole.webpagetest.org/),
    the 4xx code will lead to WordPress error page, and the 5xx will pretend 
    an error.

* **Remove settings at uninstallation**  
    If you checked this option, all settings will be removed when this plugin
    is uninstalled for clean uninstalling.

= Using with IP2Location WordPress Plugins =

After installing IP2Location WordPress Plugins, this plugin should be once 
deactivated and then activated.

== Screenshots ==

1. **IP Geo Plugin** - Settings.
2. **IP Geo Plugin** - Statistics.
3. **IP Geo Plugin** - Search.
4. **IP Geo Plugin** - Attribution.

== Changelog ==

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

== Arbitrary section ==

Thanks for providing these great services for free.

* [http://freegeoip.net/][freegeoip]    (free)
* [http://ipinfo.io/][ipinfo]           (free)
* [http://www.telize.com/][Telize]      (free)
* [http://www.iptolatlng.com/][IP2LL]   (free)
* [http://ip-json.rhcloud.com/][IPJson] (free)
* [http://xhanch.com/][Xhanch]          (free)
* [http://mshd.net/][mshdnet]           (free)
* [http://www.geoplugin.com/][geoplugin](free, need an attribution link)
* [http://ip-api.com/][ipapi]           (free for non-commercial use)
* [http://smart-ip.net/][smartip]       (free for personal and non-commercial use)
* [http://ipinfodb.com/][IPInfoDB]      (free for registered user, need API key)

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
[BHS]: http://blackhole.webpagetest.org/
[ISO]: http://en.wikipedia.org/wiki/ISO_3166-1_alpha-2#Officially_assigned_code_elements "ISO 3166-1 alpha-2 - Wikipedia, the free encyclopedia"
[RFC]: http://tools.ietf.org/html/rfc2616#section-10 "RFC 2616 - Hypertext Transfer Protocol -- HTTP/1.1"
