<?php
/**
 * YouTrack operations
 */
class YouTrackBase
{
    private $_repository;
    private $_sessionId;
    private $_youtrackUrl;
    private $_youtrackLogin;
    private $_youtrackPassword;
    private $_prefix = '';

    /**
     * @param $config
     * @param $prefix
     */
    function __construct($config = null, $prefix = '')
    {
        $this->_prefix = $prefix;
        $cfgPath = realpath(__DIR__.'/../../../../.ytconfig');
        $this->tryLoadConfig($cfgPath);
        $this->tryLoadConfig(realpath('.ytconfig'));
        if (isset($config['uri']))
            $this->_youtrackUrl      = $config['uri'];
        if (isset($config['login']))
            $this->_youtrackLogin    = $config['login'];
        if (isset($config['password']))
            $this->_youtrackPassword = $config['password'];
    }

    private function tryLoadConfig($cfgPath) {
        if (file_exists($cfgPath))
        {
            $fcont = file_get_contents($cfgPath);
            $cfg = json_decode($fcont, true);
            if ($cfg)
            {
                if (isset($cfg['uri']))
                    $this->_youtrackUrl = $cfg['uri'];
                if (isset($cfg['login']))
                    $this->_youtrackLogin = $cfg['login'];
                if (isset($cfg['password']))
                    $this->_youtrackPassword = $cfg['password'];
            }
        }
    }

    public function getLogin()
    {
        return $this->_youtrackLogin;
    }

    private function getUserNameFromXml($xml)
    {
        preg_match('/login="([a-zA-Z]*)"/mi', $xml, $matches);
        return count($matches) ? $matches[1] : '';
    }

    private function getUserEmailFromRawString($string)
    {
        preg_match('/[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,4}\b/i', $string, $matches);
        return count($matches) ? $matches[0] : '';
    }

    private function curlExecute($url, $isPostRequest, $needHeaders)
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_COOKIE, $this->_sessionId);
        curl_setopt($ch, CURLOPT_POST, $isPostRequest);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        if ($needHeaders)
        {
            curl_setopt($ch, CURLOPT_VERBOSE, 1);
            curl_setopt($ch, CURLOPT_HEADER, 1);
        }
        $result = curl_exec($ch);
        curl_close($ch);
        return $result;
    }

    private function getSessionIdFromResponse($response)
    {
        preg_match('/^Set-Cookie: \s*([^;]*)/mi', $response, $m);
        parse_str($m[1], $cookies);
        return $m[1];
    }

    private function logInYoutrack()
    {
        $url = $this->_youtrackUrl
            . "/rest/user/login?login=" . $this->_youtrackLogin
            . "&password="              . $this->_youtrackPassword;
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_VERBOSE, 1);
        curl_setopt($ch, CURLOPT_HEADER, 1);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, 1);
        $result = curl_exec($ch);
        curl_close($ch);
        return $this->getSessionIdFromResponse($result);
    }

    private function getYoutrackUserNameFromEmail($email)
    {
        $url = $this->_youtrackUrl . "/rest/admin/user?q=" . urlencode($email);
        $userInfo = $this->curlExecute($url, false, false);
        return $this->getUserNameFromXml($userInfo);
    }

    private function getIssueCommandFromMessage($issueName, $message)
    {
        preg_match("/\#" . $issueName . "(?P<command>[^\#]*)((?: #)|(?:$))/mi", $message, $matches);
        return $matches['command'];
    }

    private function _cutNewLine($text)
    {
        return str_replace('%0A', '', $text);
    }

    /**
     * Send comment for issue based on commit
     * @param $issueName
     * @param $commit
     */
    public function sendCommentForIssue($issueName, $commit)
    {
        $commentText = $this->_prefix . ' ' . $commit->message;
        $comment = urlencode($commentText);
        $runAs = $this->getYoutrackUserNameFromEmail(
            $this->getUserEmailFromRawString($commit->raw_author));

        $url = $this->_youtrackUrl . "/rest/issue/" . $issueName .
            "/execute?runAs=$runAs&comment=$comment";

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_COOKIE, $this->_sessionId);
        $result = curl_exec($ch);
    }

    private function executeCommand($taskName, $commit)
    {
        $this->sendCommentForIssue($taskName, $commit);
    }

    public static function getIssuesMatchesList($message, &$matches)
    {
        preg_match_all("/\#(?P<issue>[a-z]+\-\d+)/i", $message, $matches);
    }

    public function parseCommand($commit)
    {
        $matches = array();
        echo 'Message: '.$commit->message.'<br>';

        static::getIssuesMatchesList($commit->message, $matches);
        foreach ($matches['issue'] as $issue)
        {
            $this->executeCommand($issue, $commit);
        }
    }

    public function preLogin() {
        $this->_sessionId = $this->logInYoutrack();
    }
}
