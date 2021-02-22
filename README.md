# opa-symfony-middleware
PHP Symfony middleware that adds Open Policy Agent authorization to incoming requests.

This package is built for PHP v7.4 and above and Symfony v4.22 and above.

## Installation

In your Symfony app directory:

```
composer require buildsecurity/symfony-opa
```

## Usage

Add parameters that define how requests should be made to the Policy Decision Point (PDP) in your `services.yaml` file:

```
parameters:
    pdp.port: 8181
    pdp.hostname: http://localhost
    pdp.policy.path: /php
    pdp.readTimeout.milliseconds: 5000
    pdp.connectionTimeout.milliseconds: 5000
    pdp.retry.maxAttempts: 2
    pdp.retry.backoff.milliseconds: 250
```

Then register the `OpenPolicyAgent` service, again in `services.yaml`:

```
services:
    BuildSecurity\OpenPolicyAgentBundle\OpenPolicyAgent:
        arguments:
            $pdp_config:
                port: '%pdp.port%'
                hostname: '%pdp.hostname%'
                policy.path: '%pdp.policy.path%'
                readTimeout.milliseconds: '%pdp.readTimeout.milliseconds%'
                connectionTimeout.milliseconds: '%pdp.connectionTimeout.milliseconds%'
                retry.maxAttempts: '%pdp.retry.maxAttempts%'
                retry.backoff.milliseconds: '%pdp.retry.backoff.milliseconds%'
```

To add the authorization middleware to a controller method, just decorate it with the `Authorize` attribute.

##### Example
```
<?php
namespace App\Controller;

use Symfony\Component\HttpFoundation\Response;

// This is the attribute that the middleware looks for.
use BuildSecurity\OpenPolicyAgentBundle\Authorize;

// You can see some_method has been decorated using the
// Authorize attribute. The decoration resources, ['foo', 'bar']
// will be made available in the input to the OPA request.
class SomeController
{
    /**
     * @Authorize({"foo", "bar"})
     */
    public function some_method(): Response
    {
        return new Response(
            '<html><body>Authorized!</body></html>'
        );
    }
}
```
