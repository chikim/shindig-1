<?php
/**
 * Licensed to the Apache Software Foundation (ASF) under one
 * or more contributor license agreements.  See the NOTICE file
 * distributed with this work for additional information
 * regarding copyright ownership.  The ASF licenses this file
 * to you under the Apache License, Version 2.0 (the
 * "License"); you may not use this file except in compliance
 * with the License.  You may obtain a copy of the License at
 *
 *     http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing,
 * software distributed under the License is distributed on an
 * "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY
 * KIND, either express or implied.  See the License for the
 * specific language governing permissions and limitations
 * under the License.
 */

class PaymentHandler extends DataRequestHandler {

  protected static $PAYMENT_PATH = "/payment/{userId}/{groupId}/{appId}/{coin}/{options}";

  protected static $ANONYMOUS_ID_TYPE = array('viewer', 'me');
  protected static $ANONYMOUS_VIEWER = array(
      'name' => 'anonymous_user',
      'displayName' => 'Guest'
  );

  public function __construct() {
    parent::__construct('payment_service');
  }

  public function handleDelete(RequestItem $request) {
    throw new SocialSpiException("You can't delete payment.", ResponseError::$BAD_REQUEST);
  }

  public function handlePut(RequestItem $request) {
    throw new SocialSpiException("You can't put payment.", ResponseError::$BAD_REQUEST);
  }

  /**
   * Allowed end-points /payment/{userId}+/{groupId}/{appId}
   *
   * examples: /payment/khoa.da/@self/1
   */
  public function handlePost(RequestItem $request) {
    $this->checkService();
    $request->applyUrlTemplate(self::$PAYMENT_PATH);

    $userIds = $request->getUsers();
    $groupId = $request->getGroup();
    $appId = $request->getParameter("appId");

    // Preconditions
    if (count($userIds) < 1) {
      throw new IllegalArgumentException("No userId specified");
    } 

    $token = $request->getToken();
    $groupType = $groupId->getType();

    if($userIds[0]->getType()  == 'me')	
    {
	$user_id = $request->getParameter("platformUserId");
	$app_id = $request->getParameter("app_id");
	$callbackUrl = $request->getParameter("callbackUrl");
	$finishPageUrl = $request->getParameter("finishPageUrl");
	$message = $request->getParameter("message");
	$paymentItems = $request->getParameter("paymentItems");
        $service = $this->service;
	$ret = $service->insertPayment($user_id, $app_id, $callbackUrl, $finishPageUrl, $message, $paymentItems);
	return $ret;
    }
    else//if($userIds[0]->getType()  == 'self')
    {
	$platform_user_id = $request->getParameter("platform_user_id");
	//$app_id = $request->getParameter("app_id");
	$app_id = $request->getParameter("coin");
	$app_id = $app_id[0];
	$payment_id = $request->getParameter("payment_id");

        $service = $this->service;
	$ret = $service->insertPersonPayment($payment_id, $app_id, $platform_user_id);
        // redirect to callback url
	header("location: $ret");
	//return $ret;
    }    
    //return self::$ANONYMOUS_VIEWER;
  }

  /**
   * Allowed end-points /payment/{userId}+/{groupId}/{appId}/{coin}
   *
   * examples: /payment/khoa.da/@self/1/100
   */
  public function handleGet(RequestItem $request) {
    $this->checkService();
    $request->applyUrlTemplate(self::$PAYMENT_PATH);

    $userIds = $request->getUsers();
    $groupId = $request->getGroup();
    $appId = $request->getParameter("appId");
    $paymentId = $request->getParameter("coin");
    $token = $request->getToken();

    // Preconditions
    if (count($userIds) < 1) {
      throw new IllegalArgumentException("No userId specified");
    } 
    
    $service = $this->service;
    $ret = $service->getPayment($userIds, $groupId, $appId, $paymentId, $token);
    return $ret;
  }

  private function getCoin(RequestItem $request) {

    $this->checkService();
    $request->applyUrlTemplate(self::$PAYMENT_PATH);

    $userIds = $request->getUsers();
    $groupId = $request->getGroup();
    $appId = $request->getParameter("appId");
    $coin = $request->getParameter("coin");
    // Preconditions
    if (count($userIds) < 1) {
      throw new IllegalArgumentException("No userId specified");
    } 

    $token = $request->getToken();
    $groupType = $groupId->getType();
	
    if (count($coin) > 0) {
       $service = $this->service;
       $ret = $service->updateCoin($userIds, $groupId, $appId, $coin, $token);
       return $ret;
       //return $coin;
    }

    $options = new CollectionOptions();
    $options->setSortBy($request->getSortBy());
    $options->setSortOrder($request->getSortOrder());
    $options->setFilterBy($request->getFilterBy());
    $options->setFilterOperation($request->getFilterOperation());
    $options->setFilterValue($request->getFilterValue());
    $options->setStartIndex($request->getStartIndex());
    $options->setCount($request->getCount());

    // handle Anonymous Viewer exceptions
    $containAnonymousUser = false;
    if ($token->isAnonymous()) {
      // Find out whether userIds contains
      // a) @viewer, b) @me, c) SecurityToken::$ANONYMOUS
      foreach ($userIds as $key=>$id) {
        if (in_array($id->getType(), self::$ANONYMOUS_ID_TYPE) ||
            (($id->getType() == 'userId') && ($id->getUserId($token) == SecurityToken::$ANONYMOUS))) {
          $containAnonymousUser = true;
          unset($userIds[$key]);
        }
      }
      if ($containAnonymousUser) {
        $userIds = array_values($userIds);
        if ($groupType != 'self') {
          throw new Exception("Can't get information of others people.");
        }
      }
    }
    if ($containAnonymousUser && (count($userIds) == 0)) {
      return self::$ANONYMOUS_VIEWER;
    }
    
    $service = $this->service;
    $ret = $service->getCoin($userIds, $groupId, $appId, $options, $token);
    return $ret;
  }

}
