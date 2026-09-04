<?php
declare(strict_types=1);

namespace Oshim\Ui\Canvas3D\Math;

/**
 * 4x4 Matrix for 3D coordinate transformations, projections, and scene-graph world matrices.
 * Follows column-major ordering standard matching WebGL and Three.js.
 */
class Matrix4
{
    /**
     * 16 elements in column-major order.
     * @var array<int, float>
     */
    public array $elements = [];

    public function __construct(?array $elements = null)
    {
        if ($elements !== null && count($elements) === 16) {
            $this->elements = array_values(array_map('floatval', $elements));
        } else {
            $this->elements = [
                1.0, 0.0, 0.0, 0.0,
                0.0, 1.0, 0.0, 0.0,
                0.0, 0.0, 1.0, 0.0,
                0.0, 0.0, 0.0, 1.0,
            ];
        }
    }

    public static function identity(): self
    {
        return new self();
    }

    public static function translation(float $x, float $y, float $z): self
    {
        $m = self::identity();
        $m->elements[12] = $x;
        $m->elements[13] = $y;
        $m->elements[14] = $z;
        return $m;
    }

    public static function scaling(float $x, float $y, float $z): self
    {
        $m = self::identity();
        $m->elements[0] = $x;
        $m->elements[5] = $y;
        $m->elements[10] = $z;
        return $m;
    }

    public static function rotationX(float $radians): self
    {
        $m = self::identity();
        $c = cos($radians);
        $s = sin($radians);
        $m->elements[5] = $c;
        $m->elements[6] = $s;
        $m->elements[9] = -$s;
        $m->elements[10] = $c;
        return $m;
    }

    public static function rotationY(float $radians): self
    {
        $m = self::identity();
        $c = cos($radians);
        $s = sin($radians);
        $m->elements[0] = $c;
        $m->elements[2] = -$s;
        $m->elements[8] = $s;
        $m->elements[10] = $c;
        return $m;
    }

    public static function rotationZ(float $radians): self
    {
        $m = self::identity();
        $c = cos($radians);
        $s = sin($radians);
        $m->elements[0] = $c;
        $m->elements[1] = $s;
        $m->elements[4] = -$s;
        $m->elements[5] = $c;
        return $m;
    }

    public static function rotationFromEuler(Euler $euler): self
    {
        $rx = self::rotationX($euler->x);
        $ry = self::rotationY($euler->y);
        $rz = self::rotationZ($euler->z);

        if ($euler->order === 'XYZ') {
            return $rx->multiply($ry)->multiply($rz);
        }
        if ($euler->order === 'YXZ') {
            return $ry->multiply($rx)->multiply($rz);
        }
        if ($euler->order === 'ZYX') {
            return $rz->multiply($ry)->multiply($rx);
        }
        return $rx->multiply($ry)->multiply($rz);
    }

    public static function compose(Vector3 $position, Euler $rotation, Vector3 $scale): self
    {
        $trans = self::translation($position->x, $position->y, $position->z);
        $rot = self::rotationFromEuler($rotation);
        $sc = self::scaling($scale->x, $scale->y, $scale->z);

        return $trans->multiply($rot)->multiply($sc);
    }

    public static function perspective(float $fovDegrees, float $aspect, float $near, float $far): self
    {
        $top = $near * tan(deg2rad($fovDegrees) / 2.0);
        $height = 2.0 * $top;
        $width = $aspect * $height;
        $left = -$width / 2.0;
        $right = $width / 2.0;
        $bottom = -$top;

        $x = (2.0 * $near) / ($right - $left);
        $y = (2.0 * $near) / ($top - $bottom);
        $a = ($right + $left) / ($right - $left);
        $b = ($top + $bottom) / ($top - $bottom);
        $c = -($far + $near) / ($far - $near);
        $d = -(2.0 * $far * $near) / ($far - $near);

        $m = new self();
        $m->elements = [
            $x,  0.0, 0.0, 0.0,
            0.0, $y,  0.0, 0.0,
            $a,  $b,  $c, -1.0,
            0.0, 0.0, $d,  0.0,
        ];
        return $m;
    }

    public static function orthographic(float $left, float $right, float $top, float $bottom, float $near, float $far): self
    {
        $w = 1.0 / ($right - $left);
        $h = 1.0 / ($top - $bottom);
        $p = 1.0 / ($far - $near);

        $x = ($right + $left) * $w;
        $y = ($top + $bottom) * $h;
        $z = ($far + $near) * $p;

        $m = new self();
        $m->elements = [
            2.0 * $w, 0.0,      0.0,       0.0,
            0.0,      2.0 * $h, 0.0,       0.0,
            0.0,      0.0,     -2.0 * $p,  0.0,
            -$x,      -$y,     -$z,        1.0,
        ];
        return $m;
    }

    public static function lookAt(Vector3 $eye, Vector3 $target, Vector3 $up): self
    {
        $z = $eye->sub($target)->normalize();
        if ($z->lengthSquared() === 0.0) {
            $z->z = 1.0;
        }
        $x = $up->cross($z)->normalize();
        if ($x->lengthSquared() === 0.0) {
            $z->x += 0.0001;
            $x = $up->cross($z)->normalize();
        }
        $y = $z->cross($x);

        $m = new self();
        $m->elements = [
            $x->x, $y->x, $z->x, 0.0,
            $x->y, $y->y, $z->y, 0.0,
            $x->z, $y->z, $z->z, 0.0,
            -$x->dot($eye), -$y->dot($eye), -$z->dot($eye), 1.0,
        ];
        return $m;
    }

    public function multiply(self $b): self
    {
        $ae = $this->elements;
        $be = $b->elements;
        $res = [];

        $a11 = $ae[0];  $a12 = $ae[4];  $a13 = $ae[8];   $a14 = $ae[12];
        $a21 = $ae[1];  $a22 = $ae[5];  $a23 = $ae[9];   $a24 = $ae[13];
        $a31 = $ae[2];  $a32 = $ae[6];  $a33 = $ae[10];  $a34 = $ae[14];
        $a41 = $ae[3];  $a42 = $ae[7];  $a43 = $ae[11];  $a44 = $ae[15];

        $b11 = $be[0];  $b12 = $be[4];  $b13 = $be[8];   $b14 = $be[12];
        $b21 = $be[1];  $b22 = $be[5];  $b23 = $be[9];   $b24 = $be[13];
        $b31 = $be[2];  $b32 = $be[6];  $b33 = $be[10];  $b34 = $be[14];
        $b41 = $be[3];  $b42 = $be[7];  $b43 = $be[11];  $b44 = $be[15];

        $res[0] = ($a11 * $b11) + ($a12 * $b21) + ($a13 * $b31) + ($a14 * $b41);
        $res[4] = ($a11 * $b12) + ($a12 * $b22) + ($a13 * $b32) + ($a14 * $b42);
        $res[8] = ($a11 * $b13) + ($a12 * $b23) + ($a13 * $b33) + ($a14 * $b43);
        $res[12] = ($a11 * $b14) + ($a12 * $b24) + ($a13 * $b34) + ($a14 * $b44);

        $res[1] = ($a21 * $b11) + ($a22 * $b21) + ($a23 * $b31) + ($a24 * $b41);
        $res[5] = ($a21 * $b12) + ($a22 * $b22) + ($a23 * $b32) + ($a24 * $b42);
        $res[9] = ($a21 * $b13) + ($a22 * $b23) + ($a23 * $b33) + ($a24 * $b43);
        $res[13] = ($a21 * $b14) + ($a22 * $b24) + ($a23 * $b34) + ($a24 * $b44);

        $res[2] = ($a31 * $b11) + ($a32 * $b21) + ($a33 * $b31) + ($a34 * $b41);
        $res[6] = ($a31 * $b12) + ($a32 * $b22) + ($a33 * $b32) + ($a34 * $b42);
        $res[10] = ($a31 * $b13) + ($a32 * $b23) + ($a33 * $b33) + ($a34 * $b43);
        $res[14] = ($a31 * $b14) + ($a32 * $b24) + ($a33 * $b34) + ($a34 * $b44);

        $res[3] = ($a41 * $b11) + ($a42 * $b21) + ($a43 * $b31) + ($a44 * $b41);
        $res[7] = ($a41 * $b12) + ($a42 * $b22) + ($a43 * $b32) + ($a44 * $b42);
        $res[11] = ($a41 * $b13) + ($a42 * $b23) + ($a43 * $b33) + ($a44 * $b43);
        $res[15] = ($a41 * $b14) + ($a42 * $b24) + ($a43 * $b34) + ($a44 * $b44);

        $out = new self();
        $out->elements = $res;
        return $out;
    }

    public function multiplyVector3(Vector3 $v): Vector3
    {
        $e = $this->elements;
        $x = $v->x;
        $y = $v->y;
        $z = $v->z;
        $w = ($e[3] * $x) + ($e[7] * $y) + ($e[11] * $z) + $e[15];
        $w = ($w !== 0.0) ? (1.0 / $w) : 1.0;

        return new Vector3(
            (($e[0] * $x) + ($e[4] * $y) + ($e[8] * $z) + $e[12]) * $w,
            (($e[1] * $x) + ($e[5] * $y) + ($e[9] * $z) + $e[13]) * $w,
            (($e[2] * $x) + ($e[6] * $y) + ($e[10] * $z) + $e[14]) * $w
        );
    }

    public function toArray(): array
    {
        return $this->elements;
    }

    public function clone(): self
    {
        return new self($this->elements);
    }
}
