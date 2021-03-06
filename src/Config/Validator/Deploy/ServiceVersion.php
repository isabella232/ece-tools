<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Magento\MagentoCloud\Config\Validator\Deploy;

use Magento\MagentoCloud\App\Error;
use Magento\MagentoCloud\App\GenericException;
use Magento\MagentoCloud\Service\ServiceInterface;
use Magento\MagentoCloud\Service\ServiceFactory;
use Magento\MagentoCloud\Service\Validator as ServiceVersionValidator;
use Magento\MagentoCloud\Config\Validator;
use Magento\MagentoCloud\Config\ValidatorInterface;
use Psr\Log\LoggerInterface;

/**
 * Validates installed service versions according to version mapping.
 * @see \Magento\MagentoCloud\Service\Validator::MAGENTO_SUPPORTED_SERVICE_VERSIONS
 */
class ServiceVersion implements ValidatorInterface
{
    /**
     * @var Validator\ResultFactory
     */
    private $resultFactory;

    /**
     * @var ServiceVersionValidator
     */
    private $serviceVersionValidator;

    /**
     * @var ServiceFactory
     */
    private $serviceFactory;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @param Validator\ResultFactory $resultFactory
     * @param ServiceVersionValidator $serviceVersionValidator
     * @param ServiceFactory $serviceFactory
     * @param LoggerInterface $logger
     */
    public function __construct(
        Validator\ResultFactory $resultFactory,
        ServiceVersionValidator $serviceVersionValidator,
        ServiceFactory $serviceFactory,
        LoggerInterface $logger
    ) {
        $this->resultFactory = $resultFactory;
        $this->serviceVersionValidator = $serviceVersionValidator;
        $this->serviceFactory = $serviceFactory;
        $this->logger = $logger;
    }

    /**
     * Validates compatibility Redis and RabbitMq services with installed Magento version.
     *
     * {@inheritdoc}
     */
    public function validate(): Validator\ResultInterface
    {
        try {
            $services = [
                ServiceInterface::NAME_RABBITMQ,
                ServiceInterface::NAME_REDIS,
                ServiceInterface::NAME_DB,
                ServiceInterface::NAME_ELASTICSEARCH
            ];

            $errors = [];
            foreach ($services as $serviceName) {
                $service = $this->serviceFactory->create($serviceName);
                $serviceVersion = $service->getVersion();

                $logMsq = $serviceVersion ? 'is ' . $serviceVersion : 'is not detected';
                $this->logger->info(sprintf('Version of service \'%s\' %s', $serviceName, $logMsq));

                if ($serviceVersion !== '0' &&
                    $error = $this->serviceVersionValidator->validateService($serviceName, $serviceVersion)
                ) {
                    $errors[] = $error;
                }
            }

            if ($errors) {
                return $this->resultFactory->error(
                    'The current configuration is not compatible with this version of Magento',
                    implode(PHP_EOL, $errors),
                    Error::WARN_SERVICE_VERSION_NOT_COMPATIBLE
                );
            }
        } catch (GenericException $e) {
            return $this->resultFactory->error('Can\'t validate version of some services: ' . $e->getMessage());
        }

        return $this->resultFactory->success();
    }
}
