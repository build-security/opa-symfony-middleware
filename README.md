# opa-symfony-middleware
<p align="center"><img src="Logo-build.png" class="center" alt="build-logo" width="30%"/></p>

## Abstract
[build.security](https://docs.build.security/) provides simple development and management of the organization's authorization policy.
opa-symfony-middleware is a PHP Symfony middleware intended for performing authorizing requests against build.security pdp/[OPA](https://www.openpolicyagent.org/).

This package is built for PHP v8.0 and above and Symfony v4.22 and above.
## Data Flow
<p align="center"> <img src="Data%20flow.png" alt="drawing" width="60%"/></p>

## Usage

Before you start we recommend completing the onboarding tutorial.


---
**Important note**

In the following example we used our aws managed pdp instance to ease your first setup, but if you feel comfortable you are recommended to use your own pdp instance instead.
In that case, don't forget to change the **hostname** and the **port** in your code.

---
### Simple usage

In your Symfony app directory:

```
composer require buildsecurity/symfony-opa
```

Edit your `services.yaml` file - 
edit the configuration for OPA:
This will define how requests should be made to the PDP

```
parameters:
    pdp.port: 8181
    pdp.hostname: http://localhost
    pdp.policy.path: /authz/allow
    pdp.readTimeout.milliseconds: 5000
    pdp.connectionTimeout.milliseconds: 5000
    pdp.retry.maxAttempts: 2
    pdp.retry.backoff.milliseconds: 250
```

Register the `OpenPolicyAgent` service in your `services.yaml`
```
services:
    # Make the PDP configuration to the OpenPolicyAgent service.
    BuildSecurity\OpenPolicyAgentBundle\OpenPolicyAgent:
        arguments:
            $pdp_config:
                port: '%env(default:pdp.port:PDP_PORT)%'
                hostname: '%env(default:pdp.hostname:PDP_HOSTNAME)%'
                policy.path: '%env(default:pdp.policy.path:PDP_POLICY_PATH)%'
                readTimeout.milliseconds: '%env(default:pdp.readTimeout.milliseconds:PDP_READ_TIMEOUT_MS)%'
                connectionTimeout.milliseconds: '%env(default:pdp.connectionTimeout.milliseconds:PDP_CONNECTION_TIMEOUT_MS)%'
                retry.maxAttempts: '%env(default:pdp.retry.maxAttempts:PDP_RETRY_MAX_ATTEMPTS)%'
                retry.backoff.milliseconds: '%env(default:pdp.retry.backoff.milliseconds:PDP_RETRY_BACKOFF_MS)%'
```

The PDP_HOSTNAME, PDP_PORT, PDP_POLICY_PATH, PDP_READ_TIMEOUT_MS, PDP_CONNECTION_TIMEOUT_MS, PDP_RETRY_MAX_ATTEMPTS and PDP_RETRY_BACKOFF_MS environment variables, when added to your Symfony server environment, will override this service configurtion.
### Mandatory configuration

 1. `hostname`: The hostname of the Policy Decision Point (PDP)
 2. `port`: The port at which the OPA service is running
 3. `policyPath`: Full path to the policy (including the rule) that decides whether requests should be authorized
   
The `PDP_HOSTNAME`, `PDP_PORT`, `PDP_POLICY_PATH`, `PDP_READ_TIMEOUT_MS`, `PDP_CONNECTION_TIMEOUT_MS`, `PDP_RETRY_MAX_ATTEMPTS` and `PDP_RETRY_BACKOFF_MS` environment variables, when added to your Symfony server environment, will override this service configuration.

### Optional configuration
 1. `allowOnFailure`: Boolean. "Fail open" mechanism to allow access to the API in case the policy engine is not reachable. **Default is false**.
 2. `includeBody`: Boolean. Whether or not to pass the request body to the policy engine. **Default is true**.
 3. `includeHeaders`: Boolean. Whether or not to pass the request headers to the policy engine. **Default is true**
 4. `timeout`: Boolean. Amount of time to wait before request is abandoned and request is declared as failed. **Default is 1000ms**.
 5. `enable`: Boolean. Whether or not to consult with the policy engine for the specific request. **Default is true**

##### Example
To add the authorization middleware to a controller method, just decorate it with the `Authorize` attribute.
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
    #[Authorize('foo', 'bar')]
    public function some_method(): Response
    {
        return new Response(
            '<html><body>Authorized!</body></html>'
        );
    }
}
```

For more elaborated example [click here](example/README.md) 
### PDP Request example

This is what the input received by the PDP would look like.

```
{
    "input":{
        "request":{
            "headers":{
                "host":[
                    "localhost:8000"
                ],
                "user-agent":[
                    "curl\/7.74.0"
                ],
                "content-length":[
                    "0"
                ],
                "accept":[
                    "*\/*"
                ],
                "user":[
                    "charlie"
                ],
                "x-forwarded-for":[
                    "::1"
                ],
                "accept-encoding":[
                    "gzip"
                ],
                "content-type":[
                    ""
                ],
                "mod-rewrite":[
                    "On"
                ],
                "x-php-ob-level":[
                    "1"
                ]
            },
            "method":"POST",
            "path":"\/blog\/bob\/some-blog",
            "query":[
                
            ],
            "scheme":"http"
        },
        "resources":{
            "attributes":{
                "user":"bob",
                "blog_slug":"some-blog"
            },
            "requirements":[
                "blog.create"
            ]
        }
    }
}
```
If everything works well you should receive the following response:
+
```
{
    "decision_id":"ef414180-05bd-4817-9634-7d1537d5a657",
    "result":true
}
```