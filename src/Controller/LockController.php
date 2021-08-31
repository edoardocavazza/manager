<?php
/**
 * BEdita, API-first content management framework
 * Copyright 2021 ChannelWeb Srl, Chialab Srl
 *
 * This file is part of BEdita: you can redistribute it and/or modify
 * it under the terms of the GNU Lesser General Public License as published
 * by the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * See LICENSE.LGPL or <http://gnu.org/licenses/lgpl-3.0.html> for more details.
 */
namespace App\Controller;

use BEdita\SDK\BEditaClientException;
use Psr\Log\LogLevel;

/**
 * Lock Controller
 */
class LockController extends AppController
{
    /**
     * Add lock
     *
     * @return void
     */
    public function add(): void
    {
        $this->lock(true);
        $this->redirect($this->referer());
    }

    /**
     * Remove lock
     *
     * @return void
     */
    public function remove(): void
    {
        $this->lock(false);
        $this->redirect($this->referer());
    }

    /**
     * Perform lock/unlock on an object.
     *
     * @param bool $locked The value, true or false
     * @return void
     */
    protected function lock(bool $locked): void
    {
        $type = $this->request->getParam('object_type');
        $id = $this->request->getParam('id');
        $meta = compact('locked');
        $data = compact('id', 'type', 'meta');
        $payload = json_encode(compact('data'));
        try {
            $this->apiClient->patch(
                sprintf('/%s/%s', $type, $id),
                $payload,
                ['Content-Type' => 'application/json']
            );
        } catch (BEditaClientException $ex) {
            $this->log($ex, LogLevel::ERROR);
            $this->Flash->error(__($ex->getMessage()));
        }
    }
}
