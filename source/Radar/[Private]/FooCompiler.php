<?php
// TODO: TEMP TEST
namespace Solver\Radar;

class FooCompiler implements PsrxCompiler {
	/* (non-PHPdoc)
	 * @see \Solver\Radar\PsrxCompiler::compile()
	 */
	public function compile($sourcePathname, $symbolName) {
		return '<?php echo "oh hai";';
	}	
}