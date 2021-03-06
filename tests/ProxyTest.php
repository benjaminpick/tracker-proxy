<?php

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;

class ProxyTest extends PHPUnit_Framework_TestCase
{
    /**
     * @test
     */
    public function get_without_parameters_should_forward_piwik_js_content()
    {
        $response = $this->get();

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('piwik.js', $response->getBody()->getContents());
        $this->assertEquals('application/javascript; charset=UTF-8', $response->getHeader('Content-Type'));
    }

    /**
     * @test
     */
    public function piwik_js_should_not_be_updated_if_less_than_1_day()
    {
        $response = $this->get(null, $modifiedSince = new DateTime('-23 hours'));

        $this->assertEquals('', $response->getBody()->getContents());
        $this->assertEquals(304, $response->getStatusCode());
    }

    /**
     * @test
     */
    public function piwik_js_should_be_updated_if_more_than_1_day()
    {
        $response = $this->get(null, $modifiedSince = new DateTime('-25 hours'));

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('piwik.js', $response->getBody()->getContents());
        $this->assertEquals('application/javascript; charset=UTF-8', $response->getHeader('Content-Type'));
        $this->assertNotNull($response->getHeader('Last-Modified'));
    }

    /**
     * @test
     */
    public function get_with_parameters_should_forward_piwik_php()
    {
        $response = $this->get('foo=bar');

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('image/gif', $response->getHeader('Content-Type'));
    }

    /**
     * @test
     */
    public function get_with_parameters_should_forward_query_parameters()
    {
        $response = $this->get('foo=bar');

        $expected = <<<RESPONSE
array (
  'cip' => '127.0.0.1',
  'token_auth' => 'xyz',
  'foo' => 'bar',
)
RESPONSE;

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals($expected, $response->getBody()->getContents());
    }

    /**
     * @test
     */
    public function get_with_parameters_should_forward_response_code()
    {
        $response = $this->get('status=204');

        $this->assertEquals(204, $response->getStatusCode());
    }

    /**
     * @test
     */
    public function error_should_forward_error_code()
    {
        try {
            $this->get('status=404');
            $this->fail('The proxy did not return a 404 response');
        } catch (RequestException $e) {
            $this->assertEquals(404, $e->getResponse()->getStatusCode());
        }

        try {
            $this->get('status=500');
            $this->fail('The proxy did not return a 500 response');
        } catch (RequestException $e) {
            $this->assertEquals(500, $e->getResponse()->getStatusCode());
        }
    }

    private function get($query = null, DateTime $modifiedSince = null)
    {
        $client = new Client();

        if ($query) {
            $query = '?' . $query;
        }

        $headers = array();
        if ($modifiedSince) {
            $headers['If-Modified-Since'] = $modifiedSince->format(DateTime::RFC850);
        }

        $response = $client->get('http://127.0.0.1/piwik.php' . $query, array(
            'headers' => $headers,
        ));

        return $response;
    }
}
