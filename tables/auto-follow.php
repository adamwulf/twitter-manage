<table border=1>
	<tr>
		<th width=200>
			Username
		</th>
		<th width=200>
			Name
		</th>
		<th width=100>
			Followers
		</th>
		<th width=100>
			Followed
		</th>
		<th width=400>
			Bio
		</th>
		<th width=40>
			Remove
		</th>
	</tr>

<?

$my_name = $app->twitter()->screenname();
$results = $db->table("auto_follow")->find(array("screen_name" => $my_name));

while($row = $results->fetch_array()){
	echo "<tr>";
	echo "<td>" . htmlspecialchars($row["to_follow"]) . "</td>";
	echo "<td>" . htmlspecialchars($row["name"]) . "</td>";
	echo "<td><a href='?show_followers_for=" . $row["to_follow"] . "'>" . htmlspecialchars($row["followers_count"]) . "</a></td>";
	echo "<td>";
	$num_followed = $db->table("followers")->find(array("owner_account" => $my_name, 
														"found_via" => $row["to_follow"],
														"auto_followed_on" => ""),
												  array("auto_followed_on" => "!="));
	echo $num_followed->num_rows();
	echo "</td>";
	echo "<td>" . htmlspecialchars($row["description"]) . "</td>";
	echo "<td>";
	echo "<form action='" . page_self_url() . "' method=post>";
	echo "<input type='hidden' name=stop_autofollow_id value=" . $row["id"] . ">";
	echo "<input type='submit' value='X'>";
	echo "</form>";
	echo "</td>";
	echo "</tr>";
}
?>


	<tr>
		<td colspan=6>
			<form action="<?=page_self_url()?>" method=POST>
				Auto follow <input type='text' name='autofollow'>'s followers
				<input type='submit'>
			</form>
		</td>
	</tr>

</table>