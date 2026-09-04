<?php
declare(strict_types=1);

namespace Oshim\Compiler\Native;

use RuntimeException;

class Transpiler
{
    public function transpile(string $phpCode): string
    {
        $tokens = token_get_all($phpCode);
        
        $cpp = "#include <iostream>\n";
        $cpp .= "#include <string>\n\n";
        $cpp .= "using namespace std;\n\n";
        
        $inMain = false;
        
        for ($i = 0; $i < count($tokens); $i++) {
            $token = $tokens[$i];
            
            if (is_array($token)) {
                $type = $token[0];
                $value = $token[1];
                
                switch ($type) {
                    case T_OPEN_TAG:
                    case T_DECLARE:
                        break;
                        
                    case T_FUNCTION:
                        // Find function name
                        while (!isset($tokens[$i][1]) || $tokens[$i][0] !== T_STRING) {
                            $i++;
                        }
                        $funcName = $tokens[$i][1];
                        
                        if ($funcName === 'main') {
                            $inMain = true;
                            $cpp .= "int main(";
                        } else {
                            $cpp .= "auto " . $funcName . "(";
                        }
                        break;
                        
                    case T_VARIABLE:
                        $cpp .= str_replace('$', '', $value);
                        break;
                        
                    case T_ECHO:
                        $cpp .= "cout << ";
                        break;
                        
                    case T_STRING:
                        if ($value === 'int') {
                            $cpp .= "int "; // Added space
                        } elseif ($value === 'string') {
                            $cpp .= "string ";
                        } elseif ($value === 'void') {
                            $cpp .= "void ";
                        } elseif ($value === 'strict_types') {
                            // Ignore
                        } else {
                            $cpp .= $value;
                        }
                        break;
                        
                    case T_LNUMBER:
                    case T_DNUMBER:
                    case T_CONSTANT_ENCAPSED_STRING:
                        $cpp .= $value;
                        break;
                        
                    case T_RETURN:
                        $cpp .= "return "; // Added space
                        break;
                        
                    case T_WHITESPACE:
                        $cpp .= $value;
                        break;
                }
            } else {
                $char = $token;
                
                if ($char === ':') {
                    $i += 2; 
                } elseif ($char === '.') {
                    $cpp .= "+";
                } elseif ($char === ';') {
                    if (str_contains(substr($cpp, strrpos($cpp, "\n") ?: 0), "cout <<")) {
                        $cpp .= " << endl;";
                    } else {
                        $cpp .= ";";
                    }
                } else {
                    $cpp .= $char;
                }
            }
        }
        
        if (!$inMain && !str_contains($cpp, 'int main')) {
            throw new RuntimeException("OSHIM Native Compiler requires a `function main(): int` entry point.");
        }
        
        return $cpp;
    }
}
