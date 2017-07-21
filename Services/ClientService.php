<?php

namespace Pumukit\GCImporterBundle\Services;

class ClientService
{
    private $host;
    private $username;
    private $password;
    private $cookie;

    public function __construct($host = '', $username = '', $password = '')
    {
        //Comprobar init_curl??
        $this->host = ('/' == substr($host, -1)) ? substr($host, 0, -1) : $host;
        $this->username = $username;
        $this->password = $password;
        $this->login();
    }

    public function getMediaPackages()
    {
        $mp = $this->decodeJson($this->request($this->host.'/repository'));
        if (!$mp) {
            throw new \Exception('Error getting MediaPackages');
        }
        $return = array();
        foreach ($mp as $media) {
            array_push($return, $media);
        }

        return $return;
    }

    public function getMediaPackage($id)
    {
        $mp = $this->decodeJson($this->request($this->host.'/repository/'.$id));
        if (!$mp) {
            throw new \Exception('Error getting MediaPackage');
        }

        return $mp;
    }

    private function request($url = '')
    {
        $req = curl_init($url);
        curl_setopt($req, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($req, CURLOPT_FOLLOWLOCATION, false);
        curl_setopt($req, CURLOPT_CONNECTTIMEOUT, 1);
        curl_setopt($req, CURLOPT_TIMEOUT, 1);
        curl_setopt($req, CURLOPT_HTTPHEADER, array('Cookie: '.$this->cookie));
        $out['body'] = curl_exec($req);
        $out['error'] = curl_error($req);
        $out['status'] = curl_getinfo($req, CURLINFO_HTTP_CODE);
        if ($out['status'] != 200) {
            throw new \Exception(sprintf('Error %s Processing Request (%s)', $out['status'], $url), 1);
        }

        return $out['body'];
    }

    private function login()
    {
        $req = curl_init($this->host.'/auth/login');
        $post_data = array(
            'username' => $this->username,
            'password' => $this->password,
        );

        curl_setopt($req, CURLOPT_POSTFIELDS, $post_data);
        curl_setopt($req, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($req, CURLOPT_FOLLOWLOCATION, false);
        curl_setopt($req, CURLOPT_CONNECTTIMEOUT, 1);
        curl_setopt($req, CURLOPT_TIMEOUT, 1);
        curl_setopt($req, CURLOPT_HEADER, 1);
        $out['body'] = curl_exec($req);
        $out['error'] = curl_error($req);
        $out['status'] = curl_getinfo($req, CURLINFO_HTTP_CODE);

        preg_match_all('/^Set-Cookie:\s*([^;]*)/mi', $out['body'], $matches);
        $cookies = array();
        foreach ($matches[1] as $item) {
            parse_str($item, $cookie);
            $cookies = array_merge($cookies, $cookie);
        }
        //HTTP 302 code == Login OK, redirecting to index.html
        if ($out['status'] != 302) {
            if ($out['status'] != 200) {
                throw new \Exception(sprintf('Error %s Processing Request (%s)', $out['status'], $this->host.'/auth/login'), 1);
            } else {
                throw new \Exception(sprintf('Galicaster Web Panel Authentication Failed', $out['status'], $this->host.'/auth/login'), 1);
            }
        }
        $this->cookie = 'session='.$cookies['session'];
    }

    private function decodeJson($jsonString = '')
    {
        $decode = json_decode($jsonString, true);
        if (!($decode)) {
            throw new \Exception('JSON decoding error');
        }

        return $decode;
    }

    public function getHost()
    {
        return $this->host;
    }
}
