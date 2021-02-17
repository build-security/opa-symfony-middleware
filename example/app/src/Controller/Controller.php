<?php
namespace App\Controller;

use Symfony\Component\HttpFoundation\Response;

use BuildSecurity\OpenPolicyAgentBundle\Authorize;


class Controller
{
    #[Authorize('read', 'write')]
    public function randomNumber(): Response
    {
        $number = random_int(0, 100);

        return new Response(
            '<html><body>Lucky number: '.$number.'</body></html>'
        );
    }

    #[Authorize('read')]
    public function constantNumber(): Response
    {
        return new Response(
            '<html><body>Just a number: 42 </body></html>'
        );
    }
}