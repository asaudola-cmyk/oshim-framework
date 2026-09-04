<?php
declare(strict_types=1);

namespace Tests\Unit;

use Oshim\Testing\TestCase;
use Oshim\Ai\Tensor\Tensor;

final class TensorMathTest extends TestCase
{
    public function testTensorCreationAndGEMMMatmul(): void
    {
        // Matrix A: 2x3
        $a = Tensor::from2D([
            [1.0, 2.0, 3.0],
            [4.0, 5.0, 6.0],
        ]);

        // Matrix B: 3x2
        $b = Tensor::from2D([
            [7.0, 8.0],
            [9.0, 1.0],
            [2.0, 3.0],
        ]);

        // C = A x B: [2x2] -> [[31, 19], [85, 55]]
        $c = $a->matmul($b);

        $this->assertSame([2, 2], $c->getShape());
        $matrix = $c->to2DArray();
        $this->assertSame(31.0, $matrix[0][0]);
        $this->assertSame(19.0, $matrix[0][1]);
        $this->assertSame(85.0, $matrix[1][0]);
        $this->assertSame(55.0, $matrix[1][1]);
    }

    public function testSoftmaxAndLayerNorm(): void
    {
        $t = Tensor::from1D([1.0, 2.0, 3.0]);
        $sm = $t->softmax();

        $data = $sm->getData();
        $sum = array_sum($data);
        $this->assertTrue(abs($sum - 1.0) < 1e-5, "Softmax sum must equal 1.0 (got {$sum})");
        $this->assertTrue($data[2] > $data[1] && $data[1] > $data[0]);

        $ln = $t->layerNorm();
        $lnData = $ln->getData();
        $this->assertCount(3, $lnData);
    }

    public function testInt8Quantization(): void
    {
        $t = Tensor::from1D([-5.0, 0.0, 10.0, 20.0]);
        $q = $t->quantizeInt8();

        $this->assertArrayHasKey('quantized_data', $q);
        $this->assertArrayHasKey('scale', $q);
        $this->assertCount(4, $q['quantized_data']);
    }
}
