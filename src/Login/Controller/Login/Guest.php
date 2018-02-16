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
namespace Amazon\Login\Controller\Login;

use Amazon\Core\Domain\AmazonCustomer;

class Guest extends \Amazon\Login\Controller\Login
{
    /**
     * {@inheritdoc}
     */
    public function execute()
    {
        if (!$this->isValidToken()) {
            return $this->getRedirectLogin();
        }

        $amazonCustomer = $this->getAmazonCustomer();
        if ($amazonCustomer) {
            $this->storeUserInfoToSession($amazonCustomer);
        }

        return $this->getRedirectAccount();
    }

    /**
     * @param AmazonCustomer $amazonCustomer
     * @return void
     */
    protected function storeUserInfoToSession(AmazonCustomer $amazonCustomer)
    {
        $this->customerSession->setAmazonCustomer($amazonCustomer);
    }
}
