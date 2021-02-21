## Example

This example demonstrates usage of the OPA Symfony middleware to enforce Role Based Access Control (RBAC) on endpoints of a basic RESTful "blogging app" with [three kinds of routes](./app/config/routes.yaml):

1. Viewing blogs, using `GET` methods on `/blog/{user}/{blog-slug}` resources
2. Creating, updating or deleting blogs using `POST`, `PUT` and `DELETE` methods on the same resources
3. Viewing an "admin console" on `/admin/console`

We'll pass a HTTP request header -- `user` from our mock client to identify who is making the request. In production, for example, this could be replaced by a JSON Web Token, or any other authentication model that fits your infrastructure.

The authz policy that we're going to load into the Policy Decision Point (PDP) is defined to enforce different levels of access to three kinds of roles:

1. The `anyone` role can be anyone. Even if the `user` header is missing in the request, permission to view blogs is granted.
2. The `member` role is assigned to registered users, who can create, update and delete _their own blogs_, but can only view other's blogs.
3. The `admin` role is assigned to admins, who can access the admin console, as well as create, update and delete _anyone's blogs_.

#### Setting up the Policy Decision Point (PDP)

Let's start up the OPA server using Docker on a separate terminal.

```
docker pull openpolicyagent/opa
docker run -p 8181:8181 openpolicyagent/opa \
    run --server --log-level debug
```

Running with debug logs will show you full authz request payloads.

Then, clone and `cd` into the `/example` directory of this repository, and run,

```
curl --location --request PUT 'http://localhost:8181/v1/data/datasources/RBAC' \
    --data-binary "@./policy/rbac.json"
```

This loads the [RBAC data](./policy/rbac.json) into the PDP, which becomes part of the _authorization context_. Any data from any source can be loaded in order to inform authorization decisions. Next, we load [the policy](./policy/symfony_authz.rego),

```
curl --location --request PUT 'http://localhost:8181/v1/policies/symfony/authz' \
    --data-binary "@./policy/symfony_authz.rego"
```

And that's it! The Symfony middleware can now make authz requests to the PDP, and based on the authz policy, the input sent with the request, and other data available to it, the PDP will return an authz response.

#### Setting up the Symfony server

- [Install Symfony](https://symfony.com/doc/current/setup.html)

Make sure you're in the `/example` directory of this repository, and run

```
symfony serve --dir=app
```

Your app is now running.

#### How it works

Take a look at the [RBAC data](./policy/rbac.json).

You'll notice a few things:
- There are three users, Alice, Bob and Charlie.
- Alice is an admin, whereas Bob and Charlie are members.
- Permissions define the access limits of a role.
- Sub-roles define a hierarchy of roles.

The [policy file](./policy/symfony_authz.rego) contains the logic that makes our decisions, along with useful comments that show each step.

You may notice the `input` object in the policy. This is what it looks like when our middleware sends it as payload to the PDP:

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

It contains:
- HTTP request information, including headers, method, query values and query path.
- `resource.attributes` -- these are the route parameters and values -- in this case, `user` and `blog_slug`
- `resource.requirements` -- these are the authz requirements defined on [the controller](./app/src/Controller/Controller.php) using the middleware. The PDP makes sure that the requester _role_ has the necessary _permissions_ to fulfill the _requirements_ for this controller.

#### Try it out!

##### View a blog

```
curl --location --request GET 'http://localhost:8000/blog/bob/some-blog'
```

Even though we didn't pass a `user` header with the request, we can view the blog.

##### Create and update blog

```
curl --location --request POST 'http://localhost:8000/blog/bob/some-blog'
```

That doesn't work! Since we're running the Symfony debug server, we get a generated HTML page describing `AccessDeniedHttpException` and a strack trace. In production, you would have a Symfony `kernel.exception` handler that generates an appropriate HTML page for your users, or redirect them somewhere else, and so on.

Let's try making this request as Bob instead,

```
curl --location --request POST 'http://localhost:8000/blog/bob/some-blog' \
    --H 'user: bob'
```

That works. Can Charlie update this blog?

```
curl --location --request PUT 'http://localhost:8000/blog/bob/some-blog' \
    --H 'user: charlie'
```

No, members can only create, update and delete _their own_ blogs. Alice on the other hand...

##### Admin access

```
curl --location --request DELETE 'http://localhost:8000/blog/charlie/some-other-blog' \
    --H 'user: alice'
```

Since Alice is the admin, she's allowed to delete Charlie's blog.

Finally, we see that only Alice can view admin console.

```
curl --location --request GET 'http://localhost:8000/admin/console' \
    --H 'user: alice'
```