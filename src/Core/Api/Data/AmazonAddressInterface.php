<?php
/**
 * Copyright 2016 Amazon.com, Inc. or its affiliates. All Rights Reserved.
 *
 * Licensed under the Apache License, Version 2.0 (the "License").
 * You may not use this file except in compliance with the License.
 * A copy of the License is located at
 *
 *  http://aws.amazon.com/apache2.0
 *
 * or in the "license" file accompanying this file. This file is distributed
 * on an "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either
 * express or implied. See the License for the specific language governing
 * permissions and limitations under the License.
 */
namespace Amazon\Core\Api\Data;

/**
 * @api
 */
interface AmazonAddressInterface
{
    /**
     * Get first name
     *
     * @return string
     */
    public function getFirstName();

    /**
     * Get last name
     *
     * @return string
     */
    public function getLastName();

    /**
     * Get address lines
     *
     * @return array
     */
    public function getLines();

    /**
     * Get an address line
     *
     * @param int $lineNumber
     * @return null|string
     */
    public function getLine($lineNumber);

    /**
     * Get city
     *
     * @return string
     */
    public function getCity();

    /**
     * Get state
     *
     * @return string
     */
    public function getState();

    /**
     * Get postal code
     *
     * @return string
     */
    public function getPostCode();

    /**
     * Get country code
     *
     * @return string
     */
    public function getCountryCode();

    /**
     * Get telephone
     *
     * @return string
     */
    public function getTelephone();

    /**
     * Get company name
     *
     * @return string
     */
    public function getCompany();
}
