<?php
require_once 'page.php';

class ApiDocPage extends Page {
	private static string $class;
	private static string $name;
	private static string $prefix;

	/**
	 * @param $class Name of the API class being documented (exact name since it is used to access functions)
	 */
	public function __construct(string $class) {
		self::$class = $class;
		self::$prefix = substr($_SERVER['SCRIPT_NAME'], 0, -4) . '/';
		$name = explode('/', $_SERVER['SCRIPT_NAME']);
		self::$name = $name[count($name) - 1];
		self::$name = substr(self::$name, 0, -4);  // remove .php
		parent::__construct(self::$name . ' api');
	}

	/**
	 * Output main API documentation content.
	 */
	protected static function MainContent(): void {
		$apiClass = self::$class;
?>
		<h1><?= self::$name; ?> api</h1>
	<?php
		foreach ($apiClass::GetEndpointDocumentation() as $endpoint)
			self::ShowEndpointDocumentation($endpoint);
	}

	private static function ShowEndpointDocumentation(EndpointDocumentation $endpoint): void {
	?>
		<h2 id=<?= $endpoint->Method; ?>-<?= $endpoint->Name; ?>>
			<span class=httpmethod><?= $endpoint->Method; ?></span> <span class=apiprefix><?= self::$prefix; ?></span><span class=apiendpoint><?= $endpoint->Name; ?></span><span class=apipath><?= self::GetEndpointPath($endpoint->PathParameters); ?></span>
		</h2>
		<p><?= $endpoint->Documentation; ?></p>
		<dl class=parameters>
			<?php
			foreach ($endpoint->PathParameters as $param)
				self::ShowParameterDocumentation($param);
			?>
		</dl>
		<?php
		if ($endpoint->BodyFormat != 'none') {
		?>
			<p>
				This endpoint expects a request body in <?= $endpoint->BodyFormat; ?> format.
				<?= $endpoint->BodyDocumentation; ?>
			</p>
			<?php
			if (count($endpoint->BodyParameters)) {
			?>
				<dl class=parameters>
					<?php
					foreach ($endpoint->BodyParameters as $param)
						self::ShowParameterDocumentation($param);
					?>
				</dl>
		<?php
			}
		}
	}

	private static function GetEndpointPath(array $params): string {
		$path = '';
		foreach ($params as $param) {
			$segment = "/<code>$param->Name</code>";
			if (!$param->Required)
				$segment = "[$segment]";
			$path .= $segment;
		}
		return $path;
	}

	private static function ShowParameterDocumentation(ParameterDocumentation $param) {
		?>
		<dt><code><?= $param->Name; ?></code></dt>
		<dd><?= $param->Documentation; ?> <?= $param->Required ? 'required' : 'optional'; ?>, <?= $param->Type; ?>.</dd>
<?php
	}
}

class EndpointDocumentation {
	public string $Method;
	public string $Name;
	public string $Documentation;
	public array $PathParameters = [];
	public string $BodyFormat;
	public string $BodyDocumentation;
	public array $BodyParameters = [];

	public function __construct(string $method, string $name, string $documentation, string $bodyFormat = 'none', string $bodyDocumentation = '') {
		$this->Method = $method;
		$this->Name = $name;
		$this->Documentation = $documentation;
		$this->BodyFormat = $bodyFormat;
		$this->BodyDocumentation = $bodyDocumentation;
	}
}

class ParameterDocumentation {
	public string $Name;
	public string $Type;
	public string $Documentation;
	public bool $Required;

	public function __construct(string $name, string $type, string $documentation, bool $required = false) {
		$this->Name = $name;
		$this->Type = $type;
		$this->Documentation = $documentation;
		$this->Required = $required;
	}
}
