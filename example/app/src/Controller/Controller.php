<?php
namespace App\Controller;

use Symfony\Component\HttpFoundation\Response;

use BuildSecurity\OpenPolicyAgentBundle\Authorize;


class Controller
{
    #[Authorize('blog.view')]
    public function viewBlog(string $user, string $blog_slug) {
        return new Response(
            'You are viewing '.$blog_slug.' by '.$user
        );
    }

    #[Authorize('blog.create')]
    public function createBlog(string $blog_slug) {
        return new Response(
            'You are creating '.$blog_slug
        );
    }

    #[Authorize('blog.edit')]
    public function editBlog(string $blog_slug) {
        return new Response(
            'You are editing '.$blog_slug
        );
    }

    #[Authorize('blog.delete')]
    public function deleteBlog(string $blog_slug) {
        return new Response(
            'You are deleting '.$blog_slug
        );
    }

    #[Authorize('admin_console.view')]
    public function viewAdminConsole() {
        return new Response(
            'You are viewing the admin console.'
        );
    }
}