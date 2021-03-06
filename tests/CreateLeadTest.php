<?php

namespace Opteck\Tests;

use Opteck\Exception;
use Opteck\Exceptions\EmailAlreadyExistsException;
use Opteck\Payload;
use Opteck\Requests\CreateLead as CreateLeadRequest;
use Opteck\Responses\CreateLead as CreateLeadResponse;

class CreateLeadTest extends TestCase
{
    public function testForbiddenCountries()
    {
        $countries = $this->apiClient->getForbiddenCountries();
        $this->assertNotEmpty($countries);
    }

    public function testSuccessfulResponse()
    {
        $json = <<<'JSON'
{"returnCode":1,"description":"Successful call","timestampGenerated":"2016-04-22T15:54:56+03:00","data":{"leadID":"160422888612","dateRegistered":"2016-04-22T15:54:55+03:00","campaign":"test_campaign","subcampaign":"test_sub_campaign","status":"New","languageCode":"EN","countryCode":"GB","currencyCode":"GBP"}}
JSON;
        $response = new CreateLeadResponse(new Payload($json));
        $this->assertTrue($response->isSuccess());
        $this->assertEquals(160422888612, $response->getLeadId());
    }

    public function testWrongRequest()
    {
        $json = <<<'JSON'
{"returnCode":3,"description":"Field not valid","invalidFields":["email"],"timestampGenerated":"2016-04-22T16:16:28+03:00"}
JSON;

        try {
            new CreateLeadResponse(new Payload($json));
        } catch (Exception $e) {
            $response = $e->getResponse();
            $this->assertFalse($response->isSuccess());
            $this->assertNotEmpty($response->getInvalidFields());
        }
    }

    public function testEmailAlreadyExists()
    {
        /** @var \Opteck\Requests\CreateLead $request */
        $request = new CreateLeadRequest([
            'email'       => 'test@gmail.com',
            'firstName'   => 'John',
            'lastName'    => 'Smith',
            'language'    => 'EN',
            'country'     => 'GB',
            'phone'       => '442088963321', // Pizza Hut Restaurant
            'campaign'    => 'campaign_test_1',
            'subCampaign' => 'sub_campaign_1',
        ]);

        /* @var \Opteck\Responses\CreateLead $response */
        try {
            $this->apiClient->createLead($request);
            $this->assertTrue(false, 'Lead with email "test@gmail.com" must not be created...');
        } catch (EmailAlreadyExistsException $e) {
            $this->assertTrue(true, 'Email already exists error handled perfectly.');
        }
    }
}
