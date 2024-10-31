<?php

declare(strict_types=1);

namespace GSMBinancePay\WC\Http;

use GSMBinancePay\WC\Exception\ConnectException;

/**
 * HTTP Client using wp http to communicate.
 */
class CurlClient implements ClientInterface
{
   
   

    /**
     * @inheritdoc
     */
    public function request(
        string $method,
        string $url,
        array  $headers = [],
        string $body = ''
    ): ResponseInterface {
        $flatHeaders = [];
        foreach ($headers as $key => $value) {
            $flatHeaders[$key] = $value;
        }

        $args = [
            'method' => strtoupper($method),
            'timeout' => 30,
            'headers' =>  $flatHeaders //add headers here
        ];
 
        if ($body !== '') {
            $args['body']=$body;
        }
          
        $response=wp_remote_request($url, $args); 

        $status = wp_remote_retrieve_response_code( $response );
       

        $responseHeaders = [];
        $responseBody = '';

        if ($response) {
             $responseBody = wp_remote_retrieve_body( $response );
             $responseString = is_string($responseBody) ? $responseBody : '';
             $headerParts = wp_remote_retrieve_headers( $response );
           //  if($headerParts!=1)
          //   {
             $responseHeaders = (array) $headerParts;

               
           //  }

            }
        else {
           // $errorMessage = curl_error($ch);
           // $errorCode = curl_errno($ch);
            $errorMessage=wp_remote_retrieve_response_message( $response );
            $errorCode=$status;

            throw new ConnectException($errorMessage, $errorCode);
        }

        return new Response($status, $responseBody, $responseHeaders);
    }
}
