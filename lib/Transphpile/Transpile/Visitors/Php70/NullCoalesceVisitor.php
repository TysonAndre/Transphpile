<?php

namespace Transphpile\Transpile\Visitors\Php70;

use PhpParser\Comment;
use PhpParser\Node;
use PhpParser\NodeVisitorAbstract;

/*
 * Converts $a ?? $b into:
 *
 *      reset([
 *          call_user_func(
 *              function($v1) { return $v1 !== null ? array($v1) : null; },
 *              $a
 *          ) ?:
 *          $b
 *      ])
 *
 * (Can't use (expr)[0] in php 5.6)
 *
 * This construct is needed because isset() only works on variables, while the null coalesce supports any expression.
 * We also add a @ operator to the variable, in case it doesn't exist so it does not throw a notice.
 *
 */

class NullCoalesceVisitor extends NodeVisitorAbstract
{

    public function leaveNode(Node $node)
    {
        if (!$node instanceof Node\Expr\BinaryOp\Coalesce) {
            return null;
        }

        // Create closure node
        $closureLHSNode = new Node\Expr\Closure(array(
            'params' => array(
                new Node\Param('v1'),
            ),
            'stmts' => array(
                new Node\Stmt\Return_(
                    new Node\Expr\Ternary(
                        new Node\Expr\Isset_(array(
                            new Node\Expr\Variable('v1'),
                        )),
                        new Node\Expr\Array_([
                            new Node\Expr\ArrayItem(
                                new Node\Expr\Variable('v1')
                            ),
                        ]),
                        new Node\Expr\ConstFetch(new Node\Name('null'))
                    )
                )
            )
        ));

        // Create call_user_func() call
        // Implementation of coalesce LHS
        $coalesceLhs = new Node\Expr\FuncCall(
            new Node\Name\FullyQualified('call_user_func'),
            array(
                $closureLHSNode,
                new Node\Expr\ErrorSuppress($node->left),
            )
        );

        $inner = new Node\Expr\Ternary($coalesceLhs, null, new Node\Expr\Array_([
            new Node\Expr\ArrayItem(
                $node->right
            ),
        ]));

        return new Node\Expr\FuncCall(
            new Node\Name\FullyQualified('reset'),
            array(
                $inner
            )
        );
    }
}
