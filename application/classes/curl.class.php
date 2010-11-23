<?php
/**
 *
 */
class cURL {

  /**
   * Class constructor
   */
  public function __construct() {
    $this->cURL = curl_init();
  }

  /**
   * Sets an option to a cURL session
   *
   * @param int $option cURL option
   * @param mixed $value value of option
   * @return reference $this
   */
  public function setOpt( $option, $value ) {
    curl_setopt($this->cURL, $option, $value);
    if ($option == CURLOPT_VERBOSE) $this->Verbose = $value;
    return $this;
  }

  /**
   * Sets an array of options to a cURL session
   *
   * @param array $options array of cURL options and values
   * @return reference $this
   */
  public function setOptArray( $options ) {
    if (is_array($options))
      foreach ($options as $key=>$value) $this->setOpt($key, $value);
    return $this;
  }

  /**
   * Sets an option to a cURL session
   *
   * @param int $retry
   * @return reference $this
   */
  public function setRetry( $retry ) {
    $this->Retry = $retry;
    return $this;
  }

  /**
   * Executes as cURL session
   *
   * @param  mixed &$res Result to return
   * @return int Error number in case of error
   */
  public function exec( &$res ) {
    if ($this->Verbose) {
      $fh = tmpfile();
      curl_setopt($this->cURL, CURLOPT_STDERR, $fh);
    }

    $retry = $this->Retry;
    $code = 0;

    while ($retry-- >= 0 AND ($code == 0 OR $code >= 400 )) {
      $res = curl_exec($this->cURL);
      $code = $this->info(CURLINFO_HTTP_CODE);
    }
    
    if ($this->Verbose) {
      @rewind($fh);
      $this->Debug = @stream_get_contents($fh);
    } else
      $this->Debug = '';

    return $this->errorNo();
  }

  /**
   * Returns an array of session information
   *
   * @param int $opt optional option to return
   * @return string|array
   */
  public function info( $opt=NULL ) {
    return isset($opt)
         ? curl_getinfo($this->cURL, $opt)
         : curl_getinfo($this->cURL);
  }

  /**
   * Reset curl (re-create)
   *
   * @return reference $this
   */
  public function reset() {
    curl_close($this->cURL);
    $this->cURL = curl_init();
    return $this;
  }

  /**
   * Returns the STDERR output in Verbose == TRUE
   *
   * @return array
   */
  public function getDebug() {
    return $this->Debug;
  }

  /**
   * Returns an array of session error numbers
   *
   * @return array of error codes
   */
  public function errorNo() {
    return curl_errno($this->cURL);
  }

  /**
   * Returns an array of errors
   *
   * @return array of error messages
   */
  public function error() {
    return curl_error($this->cURL);
  }

  /**
   * Class destructor
   */
  public function __destruct() {
    curl_close($this->cURL);
  }

  // -------------------------------------------------------------------------
  // PRIVATE
  // -------------------------------------------------------------------------

  /**
   *
   */
  private $cURL = NULL;

  /**
   *
   */
  private $Verbose = FALSE;

  /**
   *
   */
  private $Retry = 0;

  /**
   *
   */
  private $Debug = '';

}

/*

CURLOPT_AUTOREFERER
    Available since PHP 5.1.0
CURLOPT_COOKIESESSION
    Available since PHP 5.1.0
CURLOPT_DNS_USE_GLOBAL_CACHE
CURLOPT_DNS_CACHE_TIMEOUT
CURLOPT_FTP_SSL
    Available since PHP 5.2.0
CURLOPT_PRIVATE
    Available since PHP 5.2.4
CURLOPT_FTPSSLAUTH
    Available since PHP 5.1.0
CURLOPT_PORT
CURLOPT_FILE
CURLOPT_INFILE
CURLOPT_INFILESIZE
CURLOPT_URL
CURLOPT_PROXY
CURLOPT_VERBOSE
CURLOPT_HEADER
CURLOPT_HTTPHEADER
CURLOPT_NOPROGRESS
CURLOPT_NOBODY
CURLOPT_FAILONERROR
CURLOPT_UPLOAD
CURLOPT_POST
CURLOPT_FTPLISTONLY
CURLOPT_FTPAPPEND
CURLOPT_FTP_CREATE_MISSING_DIRS
CURLOPT_NETRC
CURLOPT_FOLLOWLOCATION
    This constant is not available when open_basedir or safe_mode are enabled.
CURLOPT_FTPASCII
CURLOPT_PUT
CURLOPT_MUTE
CURLOPT_USERPWD
CURLOPT_PROXYUSERPWD
CURLOPT_RANGE
CURLOPT_TIMEOUT
CURLOPT_TIMEOUT_MS
CURLOPT_TCP_NODELAY
    Available since PHP 5.2.1
CURLOPT_POSTFIELDS
CURLOPT_PROGRESSFUNCTION
    Available since PHP 5.3.0
CURLOPT_REFERER
CURLOPT_USERAGENT
CURLOPT_FTPPORT
CURLOPT_FTP_USE_EPSV
CURLOPT_LOW_SPEED_LIMIT
CURLOPT_LOW_SPEED_TIME
CURLOPT_RESUME_FROM
CURLOPT_COOKIE
CURLOPT_SSLCERT
CURLOPT_SSLCERTPASSWD
CURLOPT_WRITEHEADER
CURLOPT_SSL_VERIFYHOST
CURLOPT_COOKIEFILE
CURLOPT_SSLVERSION
CURLOPT_TIMECONDITION
CURLOPT_TIMEVALUE
CURLOPT_CUSTOMREQUEST
CURLOPT_STDERR
CURLOPT_TRANSFERTEXT
CURLOPT_RETURNTRANSFER
CURLOPT_QUOTE
CURLOPT_POSTQUOTE
CURLOPT_INTERFACE
CURLOPT_KRB4LEVEL
CURLOPT_HTTPPROXYTUNNEL
CURLOPT_FILETIME
CURLOPT_WRITEFUNCTION
CURLOPT_READFUNCTION
CURLOPT_PASSWDFUNCTION
CURLOPT_HEADERFUNCTION
CURLOPT_MAXREDIRS
CURLOPT_MAXCONNECTS
CURLOPT_CLOSEPOLICY
CURLOPT_FRESH_CONNECT
CURLOPT_FORBID_REUSE
CURLOPT_RANDOM_FILE
CURLOPT_EGDSOCKET
CURLOPT_CONNECTTIMEOUT
CURLOPT_CONNECTTIMEOUT_MS
CURLOPT_SSL_VERIFYPEER
CURLOPT_CAINFO
CURLOPT_CAPATH
CURLOPT_COOKIEJAR
CURLOPT_SSL_CIPHER_LIST
CURLOPT_BINARYTRANSFER
CURLOPT_NOSIGNAL
CURLOPT_PROXYTYPE
CURLOPT_BUFFERSIZE
CURLOPT_HTTPGET
CURLOPT_HTTP_VERSION
CURLOPT_SSLKEY
CURLOPT_SSLKEYTYPE
CURLOPT_SSLKEYPASSWD
CURLOPT_SSLENGINE
CURLOPT_SSLENGINE_DEFAULT
CURLOPT_SSLCERTTYPE
CURLOPT_CRLF
CURLOPT_ENCODING
CURLOPT_PROXYPORT
CURLOPT_UNRESTRICTED_AUTH
CURLOPT_FTP_USE_EPRT
CURLOPT_HTTP200ALIASES
CURLOPT_HTTPAUTH
CURLOPT_PROXYAUTH

CURLFTPSSL_TRY
    Available since PHP 5.2.0
CURLFTPSSL_ALL
    Available since PHP 5.2.0
CURLFTPSSL_CONTROL
    Available since PHP 5.2.0
CURLFTPSSL_NONE
    Available since PHP 5.2.0

CURLAUTH_BASIC
CURLAUTH_DIGEST
CURLAUTH_GSSNEGOTIATE
CURLAUTH_NTLM
CURLAUTH_ANY
CURLAUTH_ANYSAFE

CURLCLOSEPOLICY_LEAST_RECENTLY_USED
CURLCLOSEPOLICY_LEAST_TRAFFIC
CURLCLOSEPOLICY_SLOWEST
CURLCLOSEPOLICY_CALLBACK
CURLCLOSEPOLICY_OLDEST

CURLINFO_PRIVATE
    Available since PHP 5.2.4
CURLINFO_EFFECTIVE_URL
CURLINFO_HTTP_CODE
CURLINFO_HEADER_OUT
    Available since PHP 5.1.3
CURLINFO_HEADER_SIZE
CURLINFO_REQUEST_SIZE
CURLINFO_TOTAL_TIME
CURLINFO_NAMELOOKUP_TIME
CURLINFO_CONNECT_TIME
CURLINFO_PRETRANSFER_TIME
CURLINFO_SIZE_UPLOAD
CURLINFO_SIZE_DOWNLOAD
CURLINFO_SPEED_DOWNLOAD
CURLINFO_SPEED_UPLOAD
CURLINFO_FILETIME
CURLINFO_SSL_VERIFYRESULT
CURLINFO_CONTENT_LENGTH_DOWNLOAD
CURLINFO_CONTENT_LENGTH_UPLOAD
CURLINFO_STARTTRANSFER_TIME
CURLINFO_CONTENT_TYPE
CURLINFO_REDIRECT_TIME
CURLINFO_REDIRECT_COUNT

CURL_TIMECOND_IFMODSINCE
CURL_TIMECOND_IFUNMODSINCE
CURL_TIMECOND_LASTMOD

CURL_VERSION_IPV6
CURL_VERSION_KERBEROS4
CURL_VERSION_SSL
CURL_VERSION_LIBZ

CURLVERSION_NOW

CURLE_OK
CURLE_UNSUPPORTED_PROTOCOL
CURLE_FAILED_INIT
CURLE_URL_MALFORMAT
CURLE_URL_MALFORMAT_USER
CURLE_COULDNT_RESOLVE_PROXY
CURLE_COULDNT_RESOLVE_HOST
CURLE_COULDNT_CONNECT
CURLE_FTP_WEIRD_SERVER_REPLY
CURLE_FTP_ACCESS_DENIED
CURLE_FTP_USER_PASSWORD_INCORRECT
CURLE_FTP_WEIRD_PASS_REPLY
CURLE_FTP_WEIRD_USER_REPLY
CURLE_FTP_WEIRD_PASV_REPLY
CURLE_FTP_WEIRD_227_FORMAT
CURLE_FTP_CANT_GET_HOST
CURLE_FTP_CANT_RECONNECT
CURLE_FTP_COULDNT_SET_BINARY
CURLE_PARTIAL_FILE
CURLE_FTP_COULDNT_RETR_FILE
CURLE_FTP_WRITE_ERROR
CURLE_FTP_QUOTE_ERROR
CURLE_HTTP_NOT_FOUND
CURLE_WRITE_ERROR
CURLE_MALFORMAT_USER
CURLE_FTP_COULDNT_STOR_FILE
CURLE_READ_ERROR
CURLE_OUT_OF_MEMORY
CURLE_OPERATION_TIMEOUTED
CURLE_FTP_COULDNT_SET_ASCII
CURLE_FTP_PORT_FAILED
CURLE_FTP_COULDNT_USE_REST
CURLE_FTP_COULDNT_GET_SIZE
CURLE_HTTP_RANGE_ERROR
CURLE_HTTP_POST_ERROR
CURLE_SSL_CONNECT_ERROR
CURLE_FTP_BAD_DOWNLOAD_RESUME
CURLE_FILE_COULDNT_READ_FILE
CURLE_LDAP_CANNOT_BIND
CURLE_LDAP_SEARCH_FAILED
CURLE_LIBRARY_NOT_FOUND
CURLE_FUNCTION_NOT_FOUND
CURLE_ABORTED_BY_CALLBACK
CURLE_BAD_FUNCTION_ARGUMENT
CURLE_BAD_CALLING_ORDER
CURLE_HTTP_PORT_FAILED
CURLE_BAD_PASSWORD_ENTERED
CURLE_TOO_MANY_REDIRECTS
CURLE_UNKNOWN_TELNET_OPTION
CURLE_TELNET_OPTION_SYNTAX
CURLE_OBSOLETE
CURLE_SSL_PEER_CERTIFICATE
CURLE_GOT_NOTHING
CURLE_SSL_ENGINE_NOTFOUND
CURLE_SSL_ENGINE_SETFAILED
CURLE_SEND_ERROR
CURLE_RECV_ERROR
CURLE_SHARE_IN_USE
CURLE_SSL_CERTPROBLEM
CURLE_SSL_CIPHER
CURLE_SSL_CACERT
CURLE_BAD_CONTENT_ENCODING
CURLE_LDAP_INVALID_URL
CURLE_FILESIZE_EXCEEDED
CURLE_FTP_SSL_FAILED

CURLFTPAUTH_DEFAULT
    Available since PHP 5.1.0
CURLFTPAUTH_SSL
    Available since PHP 5.1.0
CURLFTPAUTH_TLS
    Available since PHP 5.1.0

CURLPROXY_HTTP
CURLPROXY_SOCKS5

CURL_NETRC_OPTIONAL
CURL_NETRC_IGNORED
CURL_NETRC_REQUIRED

CURL_HTTP_VERSION_NONE
CURL_HTTP_VERSION_1_0
CURL_HTTP_VERSION_1_1

CURLM_CALL_MULTI_PERFORM

CURLM_OK
CURLM_BAD_HANDLE
CURLM_BAD_EASY_HANDLE
CURLM_OUT_OF_MEMORY
CURLM_INTERNAL_ERROR

CURLMSG_DONE

*/