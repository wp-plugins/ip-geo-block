/**
 * WP-ZEP - Zero-day exploit Prevention for wp-admin
 *
 */
/* utility object */
var IP_GEO_BLOCK_ZEP = {
	auth: 'ip-geo-block-auth-nonce',
	nonce: IP_GEO_BLOCK_AUTH.nonce || '',
	redirect: function (url) {
		if (-1 !== location.href.indexOf(url)) {
			if (this.nonce) {
				url += (url.indexOf('?') >= 0 ? '&' : '?') + this.auth + '=' + this.nonce;
			} 
			window.location.href = url;
		}
	}
};

(function ($, document) {
	function parse_uri(uri) {
		var m = uri ? uri.toString().match(
			// https://tools.ietf.org/html/rfc3986#appendix-B
			/^(?:([^:\/?#]+):)?(?:\/\/([^\/?#]*))?([^?#]*)(?:\?([^#]*))?(?:#(.*))?/
		) : [];

		// scheme :// authority path ? query # fragment
		return {
			scheme    : m[1] || '',
			authority : m[2] || '',
			path      : m[3] || '',
			query     : m[4] || '',
			fragment  : m[5] || ''
		};
	}

	// https://developer.mozilla.org/en-US/docs/Web/JavaScript/Reference/Global_Objects/encodeURIComponent
	function encodeURIComponentRFC3986(str) {
		return encodeURIComponent(str).replace(/[!'()*]/g, function (c) {
			return '%' + c.charCodeAt(0).toString(16);
		});
	}

	function is_admin(url, query) {
		var uri = parse_uri(url ? url.toString().toLowerCase() : ''),
		    // directory traversal should be checked more strictly ?
		    path = (uri.path.replace('/\./g', '').charAt(0) === '/' ? uri.path : location.pathname);

		// explicit scheme and external domain
		if (/https?/.test(uri.scheme) && uri.authority !== location.host.toLowerCase()) {
			return -1; // -1: external
		}

		var regexp = new RegExp(
			'(?:/wp-admin/|' + IP_GEO_BLOCK_AUTH.plugins + '|' + IP_GEO_BLOCK_AUTH.themes + ')'
		);

		// possibly scheme is `javascript` or path is `;`
		return (uri.scheme || uri.path || uri.query) && path.match(regexp) ? 1 : 0;
	}

	function query_args(uri, args) {
		return (uri.scheme ? uri.scheme + '://' : '') +
		       (uri.authority + uri.path + '?' + args.join('&'));
	}

	function add_query_nonce(src, nonce) {
		var uri = parse_uri(src), data;
		data = uri.query ? uri.query.split('&') : [];
		data.push(IP_GEO_BLOCK_ZEP.auth + '=' + encodeURIComponentRFC3986(nonce));
		return query_args(uri, data);
	}

	function sanitize(str) {
		return str ? str.toString().replace(/[&<>"']/g, function (match) {
			return {
				'&' : '&amp;',
				'<' : '&lt;',
				'>' : '&gt;',
				'"' : '&quot;',
				"'" : '&#39;'
			}[match];
		}) : '';
	}

	$(document).ajaxSend(function (event, jqxhr, settings) {
		var nonce = IP_GEO_BLOCK_ZEP.nonce;
		if (nonce && is_admin(settings.url, null/*settings.data*/) === 1) {
			// multipart/form-data (XMLHttpRequest Level 2)
			// IE10+, Firefox 4+, Safari 5+, Android 3+
			if (typeof window.FormData !== 'undefined' &&
			    settings.data instanceof FormData) {
				settings.data.append(IP_GEO_BLOCK_ZEP.auth, nonce);
			}

			// application/x-www-form-urlencoded
			else {
				// Behavior of jQuery Ajax
				// method  url  url+data data
				// GET    query  query   data
				// POST   query  query   data
				var uri = parse_uri(settings.url), data;
				if (typeof settings.data === 'undefined' || uri.query) {
					data = uri.query ? uri.query.split('&') : [];
					data.push(IP_GEO_BLOCK_ZEP.auth + '=' + encodeURIComponentRFC3986(nonce));
					settings.url = query_args(uri, data);
				} else {
					data = settings.data ? settings.data.split('&') : [];
					data.push(IP_GEO_BLOCK_ZEP.auth + '=' + encodeURIComponentRFC3986(nonce));
					settings.data = data.join('&');
				}
			}
		}
	});

	$(function () {
		var nonce = IP_GEO_BLOCK_ZEP.nonce;
		if (nonce) {
			$body = $('body');

			$body.find('img').each(function (index) {
				var src = $(this).attr('src');

				// if admin area
				if (is_admin(src, src) === 1) {
					$(this).attr('src', add_query_nonce(src, nonce));
				}
			});

			$body.on('click', 'a', function (event) {
				var href = $(this).attr('href'), // String or undefined
				    admin = is_admin(href, href);

				// if admin area
				if (admin === 1) {
					$(this).attr('href', add_query_nonce(href, nonce));
				}

				// if external
				else if (admin === -1) {
					// redirect with no referrer not to leak out the nonce
					var w = window.open();
					w.document.write(
						'<meta name="referrer" content="never" />' +
						'<meta name="referrer" content="no-referrer" />' +
						'<meta http-equiv="refresh" content="0; url=' + sanitize(this.href) + '" />'
					);
					w.document.close();
					return false;
				}
			});

			$body.on('submit', 'form', function (event) {
				var $this = $(this),
				    action = $this.attr('action');

				// if admin area
				if (is_admin(action, $this.serialize()) === 1) {
					$this.attr('action', add_query_nonce(action, nonce));
				}
			});

			$('form').each(function (index) {
				var $this = $(this), action = $this.attr('action');
				if (is_admin(action, action) === 1 &&
				    'multipart/form-data' === $this.attr('enctype')) {
					$this.append(
						'<input type="hidden" name="' + IP_GEO_BLOCK_ZEP.auth + '" value="'
						+ sanitize(nonce) + '" />'
					);
				}
			});
		}
	});
}(jQuery, document));