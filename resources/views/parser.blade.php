<!DOCTYPE html>
<html>
	<head>
		<meta charset="utf-8">
		<title>clarkwinkelmann/bugnplay-parser</title>
		<link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.6/css/bootstrap.min.css" integrity="sha384-1q8mTJOASx8j1Au+a5WDVnPi2lkFfwwEAa8hDDdjZlpLegxhjVME1fgjWPGmkzs7" crossorigin="anonymous">
	</head>
	<body>
		<h1>clarkwinkelmann/bugnplay-parser</h1>
		<table class="table table-striped">
			<thead>
				<tr>
					<th>UID</th>
					<th>Project</th>
					<th>Analysis</th>
				</tr>
			</thead>
			<tbody>
				@foreach($projects as $project)
				<tr>
					<td>{{ $project->uid }}</td>
					<td>
						<!-- If we embed in <pre> the lines don't span -->
						{!! str_replace(["\n",'    '], ['<br>', '&nbsp;&nbsp;&nbsp;&nbsp;'], e(json_encode($project, JSON_PRETTY_PRINT))) !!}
					</td>
					<td>{!! $project->technologies_analysis !!}</td>
				</tr>
				@endforeach
			</tbody>
		</table>
	</body>
</html>