<?php
namespace App\Controller;

use Symfony\Component\HttpFoundation\Response;

use BuildSecurity\OpenPolicyAgentBundle\Authorize;


class LuckyController
{
    #[Authorize('request.label')]
    public function number(): Response
    {
        $number = random_int(0, 100);

        return new Response(
            '<html><body>Lucky number: '.$number.'</body></html>'
        );
    }
}