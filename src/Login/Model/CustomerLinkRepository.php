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
namespace Amazon\Login\Model;

use Magento\Framework\Api\FilterBuilder;
use Magento\Framework\Api\Search\FilterGroup;
use Magento\Framework\Api\SearchCriteria\CollectionProcessorInterface;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Amazon\Login\Api\CustomerLinkRepositoryInterface;
use Amazon\Login\Api\Data;
use Amazon\Login\Api\Data\CustomerLinkInterface;
use Amazon\Login\Api\Data\CustomerLinkSearchResultsInterfaceFactory;
use Amazon\Login\Model\ResourceModel\CustomerLink as CustomerLinkResourceModel;
use Amazon\Login\Model\ResourceModel\CustomerLink\Collection;
use Amazon\Login\Model\ResourceModel\CustomerLink\CollectionFactory;

/**
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class CustomerLinkRepository implements CustomerLinkRepositoryInterface
{
    /**
     * @var CustomerLinkResourceModel
     */
    protected $resourceModel;

    /**
     * @var CustomerLinkFactory
     */
    protected $customerLinkFactory;

    /**
     * @var PaymentTokenSearchResultsInterfaceFactory
     */
    protected $searchResultsFactory;

    /**
     * @var \Magento\Framework\Api\FilterBuilder
     */
    protected $filterBuilder;

    /**
     * @var \Magento\Framework\Api\SearchCriteriaBuilder
     */
    protected $searchCriteriaBuilder;

    /**
     * @var CollectionFactory
     */
    protected $collectionFactory;

    /**
     * @var \Magento\Framework\Api\SearchCriteria\CollectionProcessorInterface
     */
    private $collectionProcessor;

    /**
     * @param CustomerLinkResourceModel $customerLinkFactory
     * @param CustomerLinkFactory $resourceModel
     * @param FilterBuilder $filterBuilder
     * @param SearchCriteriaBuilder $searchCriteriaBuilder
     * @param PaymentTokenSearchResultsInterfaceFactory $searchResultsFactory
     * @param CollectionFactory $collectionFactory
     * @param CollectionProcessorInterface | null $collectionProcessor
     */
    public function __construct(
        CustomerLinkResourceModel $resourceModel,
        CustomerLinkFactory $customerLinkFactory,
        FilterBuilder $filterBuilder,
        SearchCriteriaBuilder $searchCriteriaBuilder,
        CustomerLinkSearchResultsInterfaceFactory $searchResultsFactory,
        CollectionFactory $collectionFactory,
        CollectionProcessorInterface $collectionProcessor = null
    ) {
        $this->resourceModel = $resourceModel;
        $this->customerLinkFactory = $customerLinkFactory;
        $this->filterBuilder = $filterBuilder;
        $this->searchCriteriaBuilder = $searchCriteriaBuilder;
        $this->searchResultsFactory = $searchResultsFactory;
        $this->collectionFactory = $collectionFactory;
        $this->collectionProcessor = $collectionProcessor ?: $this->getCollectionProcessor();
    }

    /**
     * {@inheritdoc}
     */
    public function getById($entityId)
    {
        $customerLink = $this->customerLinkFactory->create();
        $this->resourceModel->load($customerLink, $entityId);
        return $customerLink;
    }

    /**
     * {@inheritdoc}
     */
    public function getList(\Magento\Framework\Api\SearchCriteriaInterface $searchCriteria)
    {
        $collection = $this->collectionFactory->create();
        $this->collectionProcessor->process($searchCriteria, $collection);
        $searchResults = $this->searchResultsFactory->create();
        $searchResults->setSearchCriteria($searchCriteria);
        $searchResults->setItems($collection->getItems());
        return $searchResults;
    }

    /**
     * {@inheritdoc}
     */
    public function delete(CustomerLinkInterface $customerLink)
    {
        try {
            $this->resourceModel->delete($customerLink);
        } catch (\Exception $exception) {
            throw new CouldNotDeleteException(__($exception->getMessage()));
        }
        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function deleteById($entityId)
    {
        return $this->delete($this->getById($entityId));
    }

    /**
     * {@inheritdoc}
     */
    public function save(CustomerLinkInterface $customerLink)
    {
        try {
            $this->resourceModel->save($customerLink);
        } catch (\Exception $exception) {
            throw new CouldNotSaveException(
                __('Could not save Amazon customer link: %1', $exception->getMessage()),
                $exception
            );
        }
        return $customerLink;
    }
}
