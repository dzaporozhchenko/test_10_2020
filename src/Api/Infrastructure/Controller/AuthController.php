<?php


namespace App\Api\Infrastructure\Controller;

use App\Api\Infrastructure\Event\CustomerUserAccountCreatedEvent;
use App\Api\Model\Entity\User;
use App\Resources\Api\ApiController;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Encoder\UserPasswordEncoderInterface;

class AuthController extends ApiController
{
    public function register(Request $request, UserPasswordEncoderInterface $encoder, EventDispatcherInterface $eventDispatcher)
    {
        $em = $this->getDoctrine()->getManager();
        $request = $this->transformJsonBody($request);
        $username = $request->get('username');
        $password = $request->get('password');
        $role = $request->get('role');

        if (empty($username) || empty($password) || empty($role) || ! in_array($role, ['client', 'manager'])){
            return $this->respondValidationError("Invalid Username or Password or Role (should be 'client' or 'manager')");
        }

        $user = new User();
        $user->setUsername($username);
        $user->setPassword($encoder->encodePassword($user, $password));
        $user->setRoles(array($role == 'client' ? 'ROLE_CLIENT' : 'ROLE_MANAGER'));
        $em->persist($user);
        $em->flush();

        $this->dispatchCustomerUserAccountCreationEvent($user, $eventDispatcher);


        $this->setStatusCode(201);
        return $this->response(null, sprintf('User %s successfully created', $user->getUsername()));
    }

    protected function dispatchCustomerUserAccountCreationEvent(User $user, EventDispatcherInterface $eventDispatcher)
    {
        if (! in_array('ROLE_CLIENT', $user->getRoles())) {
            return;
        }

        $event = new CustomerUserAccountCreatedEvent($user);
        $eventDispatcher->dispatch($event, 'user.customer_account_created');
    }
}